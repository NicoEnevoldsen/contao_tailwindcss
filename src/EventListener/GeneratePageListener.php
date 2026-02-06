<?php

namespace Nedev\ContaoTailwindBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Database;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\LayoutModel;
use Psr\Log\LoggerInterface;
use Contao\System;
use Contao\PageRegular;
use Symfony\Component\Filesystem\Filesystem;

#[AsHook('generatePage')]
class GeneratePageListener
{
    private $objDatabase, $project_dir, $bundle_dir, $tmp_dir, $assets_dir, $logger, $errorLogger, $tailwind_binary;

    public function __construct(){
        $this->objDatabase = Database::getInstance();
        $this->project_dir  = System::getContainer()->getParameter('kernel.project_dir');
        $this->bundle_dir = '../vendor/ne-dev/contao-tailwind-bundle';
        $this->tmp_dir      = $this->project_dir.'/system/tmp';
        $this->assets_dir   = $this->project_dir."/assets/css";
        $this->logger       = System::getContainer()->get('monolog.logger.contao.general');
        $this->errorLogger  = System::getContainer()->get('monolog.logger.contao.error');

        if(!file_exists($this->assets_dir)) mkdir($this->assets_dir);
        if(!file_exists($this->tmp_dir."/css")) mkdir($this->tmp_dir."/css");
        if(!file_exists($this->tmp_dir."/templates")) mkdir($this->tmp_dir."/templates");
        if(!file_exists($this->bundle_dir."/src/TailwindBinary")) mkdir($this->bundle_dir."/src/TailwindBinary");

        if(PHP_OS == "Linux" && php_uname('m') === 'arm64')     $this->tailwind_binary = "tailwindcss-linux-arm64";
        if(PHP_OS == "Linux" && php_uname('m') === 'x86_64')    $this->tailwind_binary = "tailwindcss-linux-x64";
        if(PHP_OS == "Darwin" && php_uname('m') === 'arm64')    $this->tailwind_binary = "tailwindcss-macos-arm64";
        if(PHP_OS == "Darwin" && php_uname('m') === 'x86_64')   $this->tailwind_binary = "tailwindcss-macos-x64";
        if(PHP_OS == "WINNT")                                         $this->tailwind_binary = "tailwindcss-windows-x64.exe";

        if($this->tailwind_binary) {
            if(!file_exists($this->bundle_dir."/src/TailwindBinary/".$this->tailwind_binary)) {
                exec("wget -O ".$this->bundle_dir."/src/TailwindBinary/".$this->tailwind_binary." https://github.com/tailwindlabs/tailwindcss/releases/latest/download/".$this->tailwind_binary." 2>&1", $output, $return_var);
                if ($return_var === 0) {
                    $this->logger->info("The Tailwind binary has been downloaded.");
                } else {
                    $this->errorLogger->error("Tailwind binary could not be downloaded.\nError-Message: ".implode("\n", $output));
                    $this->tailwind_binary = null;
                }
            }
            if($this->tailwind_binary && !is_executable($this->bundle_dir."/src/TailwindBinary/".$this->tailwind_binary)) {
                exec("chmod +x ".$this->bundle_dir."/src/TailwindBinary/".$this->tailwind_binary." 2>&1", $output, $return_var);
                if ($return_var === 0) {
                    $this->logger->info("Tailwind-Binary was not executable:\nAccess rights have been adjusted.");
                } else {
                    $this->errorLogger->error("Tailwind-Binary is not executable:\nAccess rights could not be adjusted.\nError-Message: ".implode("\n", $output));
                    $this->tailwind_binary = null;
                }
            }
        } else {
            $this->errorLogger->error("Tailwind binary not found for operating system");
        }
    }

    public function __invoke(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
    {
        if(!$layout->tailwind) return;
        if(!$this->tailwind_binary) return;

        $helper_template = $this->getHelperTemplate();
        $newest_template_time = $this->getNewestTemplateTime();

        $objTailwindFile = $this->objDatabase->prepare("SELECT * FROM tl_tailwind_settings WHERE id = ?")->execute($layout->tailwind);

        if($objTailwindFile->input_file){
            $input_file     = FilesModel::findByUuid($objTailwindFile->input_file)->path;
            $output_file    = pathinfo($input_file, PATHINFO_DIRNAME)."/".pathinfo($input_file, PATHINFO_FILENAME).".build.".pathinfo($input_file,PATHINFO_EXTENSION);
            $combined_file  = $this->tmp_dir."/tw_".$objTailwindFile->id."_combined.css";
        } else {
            $input_file = null;
            $output_file    = "assets/css/tw_".$objTailwindFile->id.".css";
            $combined_file  = $this->tmp_dir."/tw_".$objTailwindFile->id."_combined.css";
        }

        if(!file_exists($output_file) || ($input_file && filemtime($input_file) > filemtime($output_file)) || $newest_template_time > filemtime($output_file) || $objTailwindFile->tstamp > filemtime($output_file) || filemtime($helper_template) > filemtime($output_file) || $layout->tstamp > filemtime($output_file)) {

            $config = $this->generateTailwindConfig($objTailwindFile);

            $combined_css = "
                @import 'tailwindcss';
                @source '".$this->project_dir."/templates';
                @source '".$this->project_dir."/system/tmp/templates';
                @source '".$this->bundle_dir."/src/Resources/contao/templates';
                @theme {\r\n".$config."\r\n}
                html{ font-size: ".$objTailwindFile->base_font_size."px }
                .invisible { display: none !important; }
            ";
            if($input_file) $combined_css .= file_get_contents($input_file);

            file_put_contents($combined_file, $combined_css);
            touch($combined_file, time());

            @unlink($output_file);
            $command = $this->bundle_dir.'/src/TailwindBinary/'.$this->tailwind_binary.' -i '.$combined_file.' -o '.$output_file.' --minify  2>&1';
            exec($command, $output, $return_var);
            if ($return_var === 0) {
                $this->logger->info("Tailwind build successful:\n".implode("\n", $output));
                touch($output_file, time());
            } else {
                $this->logger->info("Error running Tailwind CLI:\n".implode("\n", $output));
            }

            @unlink($combined_file);
            (new Filesystem())->remove($this->assets_dir);
        }

        $GLOBALS['TL_CSS'][] = $output_file.'|static';

    }


    private function scanDirRecursive(string $path): array {
        $result = [];
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath))  $result = array_merge($result,$this->scanDirRecursive($fullPath));
            else                    $result[] = $fullPath;
        }
        return $result;
    }

    private function getNewestTemplateTime(): int {
        $newest_template_time = 0;
        $templates = $this->scanDirRecursive($this->project_dir."/templates");
        if(count($templates)>0) {
            foreach ($templates as $template) {
                if(filemtime($template) > $newest_template_time) $newest_template_time = filemtime($template);
            }
        }
        return $newest_template_time;
    }

    private function generateTailwindConfig($objTailwindFile): string {
        $config = '';
        $colors = unserialize($objTailwindFile->colors);
        if(is_array($colors) && count($colors) > 0) {
            foreach ($colors as &$color) $color = ' --color-'.html_entity_decode($color['key']).': '.html_entity_decode($color['value'])."; ";
            $config .= implode("\n", $colors)."\n";
        }
        $breakpoints = unserialize($objTailwindFile->breakpoints);
        if(is_array($breakpoints) && count($breakpoints) > 0) {
            foreach ($breakpoints as &$breakpoint) $breakpoint = ' --breakpoint-'.($breakpoint['key']).': '.($breakpoint['value'])."; ";
            $config .= implode("\n", $breakpoints)."\n";
        }
        $config .= html_entity_decode($objTailwindFile->config);
        return $config;
    }


    private function getHelperTemplate() {

        foreach ($GLOBALS['BE_MOD'] as $group) {
            foreach ($group as $module) {
                if (!empty($module['tables']) && is_array($module['tables'])) {
                    foreach ($module['tables'] as $table) {
                        \Contao\Controller::loadDataContainer($table);
                    }
                }
            }
        }

        $sqls = ["SELECT DISTINCT attributes AS classes FROM tl_form"];
        foreach ($GLOBALS['TL_DCA'] as $table => $dca) {
            if(isset($dca['fields']['cssID']))      $sqls[] = 'SELECT DISTINCT cssID AS classes FROM '.$table;
            if(isset($dca['fields']['cssClass']))   $sqls[] = 'SELECT DISTINCT cssClass AS classes FROM '.$table;
        }
        $objCss = $this->objDatabase->prepare(implode(" UNION ", $sqls))->execute();
        $classes = [];
        while ($objCss->next()){
            $field_values = @unserialize($objCss->classes);
            if(!$field_values)  $classes_string = $objCss->classes;
            else                $classes_string = $field_values[1];
            $classes = array_merge($classes, explode(" ", $classes_string));
        }

        $objContent = $this->objDatabase->prepare("
            SELECT DISTINCT unfilteredHtml AS content FROM tl_content WHERE unfilteredHtml != ''
            UNION SELECT DISTINCT html AS content FROM tl_content WHERE html != ''
            UNION SELECT DISTINCT text AS content FROM tl_content WHERE text != ''
            UNION SELECT DISTINCT html FROM tl_module WHERE html != ''
        ")->execute();
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        while($objContent->next()) {
            $dom->loadHTML($objContent->content);
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query('//*[@class]'); // jedes Element mit class-Attribut
            foreach ($nodes as $node) {
                $classes = array_merge($classes, explode(" ", $node->getAttribute("class")));
            }
        }

        if (isset($GLOBALS['TL_HOOKS']['generateTailwindCss']) && \is_array($GLOBALS['TL_HOOKS']['generateTailwindCss'])) {
            foreach ($GLOBALS['TL_HOOKS']['generateTailwindCss'] as $callback) {
                $classes = System::importStatic($callback[0])->{$callback[1]}($classes);
            }
        }

        $classes = array_unique($classes);
        $classes = array_filter($classes);
        asort($classes);

        $new_classes = '<div class="'.implode(" ", $classes).'"></div>';
        if(!file_exists($this->tmp_dir.'/templates/tailwind.html5') || file_get_contents($this->tmp_dir.'/templates/tailwind.html5') != $new_classes) {
            file_put_contents($this->tmp_dir.'/templates/tailwind.html5', $new_classes);
            touch($this->tmp_dir.'/templates/tailwind.html5', time());
            $this->logger->info('New CSS classes found in database. Tailwind helper template recreated.');
        }

        return $this->tmp_dir.'/templates/tailwind.html5';
    }

}
