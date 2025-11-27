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

#[AsHook('generatePage')]
class GeneratePageListener
{
    private $objDatabase, $project_dir, $bundle_dir, $tmp_dir, $assets_dir, $logger, $errorLogger, $tailwind_binary;

    public function __construct(){
        $this->objDatabase = Database::getInstance();
        $this->project_dir  = System::getContainer()->getParameter('kernel.project_dir');
        $this->bundle_dir   = \dirname(__DIR__, 2);
        $this->tmp_dir      = $this->project_dir.'/system/tmp';
        $this->assets_dir   = $this->project_dir."/assets/tailwind";
        $this->logger       = System::getContainer()->get('monolog.logger.contao.general');
        $this->errorLogger  = System::getContainer()->get('monolog.logger.contao.error');

        if(!file_exists($this->assets_dir)) mkdir($this->assets_dir);
        if(!file_exists($this->tmp_dir."/templates")) mkdir($this->tmp_dir."/templates");
        if(!file_exists($this->bundle_dir."/src/TailwindBinary")) mkdir($this->bundle_dir."/src/TailwindBinary");

        if(PHP_OS == "Linux" && php_uname('m') === 'arm64')     $this->tailwind_binary = "tailwindcss-linux-arm64";
        if(PHP_OS == "Linux" && php_uname('m') === 'x86_64')    $this->tailwind_binary = "tailwindcss-linux-x64";
        if(PHP_OS == "Darwin" && php_uname('m') === 'arm64')    $this->tailwind_binary = "tailwindcss-macos-arm64";
        if(PHP_OS == "Darwin" && php_uname('m') === 'x86_64')   $this->tailwind_binary = "tailwindcss-macos-x64";
        if(PHP_OS == "WINNT")                                         $this->tailwind_binary = "tailwindcss-windows-x64.exe";

        if($this->tailwind_binary) {
            if(!file_exists($this->bundle_dir."/src/TailwindBinary/".$this->tailwind_binary)) {
                exec("wget -O ".$this->bundle_dir."/src/TailwindBinary/".$this->tailwind_binary." https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64 2>&1", $output, $return_var);
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


    public function __invoke(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
    {
        if(!$layout->tailwind) return;
        if(!$this->tailwind_binary) return;

        $tailwind_ids = unserialize($layout->tailwind);
        if(!$tailwind_ids || !is_array($tailwind_ids) || count($tailwind_ids) == 0) return;

        $helper_template = $this->getHelperTemplate();


        $newest_template_time = 0;
        $templates = $this->scanDirRecursive($this->project_dir."/templates");
        if(count($templates)>0) {
            foreach ($templates as $template) {
                if(filemtime($template) > $newest_template_time) $newest_template_time = filemtime($template);
            }
        }

        foreach ($tailwind_ids as $tailwind_id) {

            $objTailwindFile = $this->objDatabase->prepare("SELECT * FROM tl_tailwind_settings WHERE id = ?")->execute($tailwind_id);

            if($objTailwindFile->input_file === null) continue;

            $input_file     = FilesModel::findByUuid($objTailwindFile->input_file)->path;
            $output_file    = $this->assets_dir."/".$objTailwindFile->id.".".pathinfo($input_file,PATHINFO_EXTENSION);
            $tailwind_file  = $this->assets_dir."/".$objTailwindFile->id."_combined.".pathinfo($input_file,PATHINFO_EXTENSION);

            if(!file_exists($output_file) || filemtime($input_file) > filemtime($output_file) || $newest_template_time > filemtime($output_file) || $objTailwindFile->tstamp > filemtime($output_file) || filemtime($helper_template) > filemtime($output_file)) {
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

                $combined_css = "@import 'tailwindcss';\r\n@source '".$this->project_dir."/templates';\r\n@source '".$this->project_dir."/system/tmp/templates';\r\n@source '".$this->bundle_dir."/src/Resources/contao/templates';\r\n@theme {\r\n".$config."\r\n}\r\nhtml{ font-size: ".$objTailwindFile->base_font_size."px }\r\n.invisible { display: none !important; }\r\n".file_get_contents($input_file);

                if(!file_exists($tailwind_file) || file_get_contents($tailwind_file) != $combined_css) {
                    file_put_contents($tailwind_file, $combined_css);
                    touch($tailwind_file, time());
                }

                $command = $this->bundle_dir.'/src/TailwindBinary/'.$this->tailwind_binary.' -i '.$tailwind_file.' -o '.$output_file.' --minify 2>&1';
                exec($command, $output, $return_var);
                if ($return_var === 0) {
                    $this->logger->info("Tailwind build successful:\n".implode("\n", $output));
                    touch($output_file, time());
                } else {
                    $this->logger->info("Error running Tailwind CLI:\n".implode("\n", $output));
                }
            }

            $output_file = "assets/tailwind/".$objTailwindFile->id.".".pathinfo($input_file,PATHINFO_EXTENSION);
            $GLOBALS['TL_CSS'][] = $output_file.'|static';
        }

    }


    private function getHelperTemplate() {
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
