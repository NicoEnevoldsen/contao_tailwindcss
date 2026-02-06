<?php



    $GLOBALS['TL_DCA']['tl_layout']['fields']['tailwind'] = [
        'exclude'    => true,
        'inputType'  => 'select',
        'options_callback' => function () {
            $arr = [];
            $obj = \Contao\Database::getInstance()->execute("SELECT id, title FROM tl_tailwind_settings ORDER BY title");
            while ($obj->next()) {
                $arr[$obj->id] = $obj->title;
            }
            return $arr;
        },
        'eval'       => ['mandatory' => false, 'multiple' => false, 'includeBlankOption'=>true],
        'sql'        => "blob NULL",
    ] ;


    $GLOBALS['TL_DCA']['tl_layout']['palettes']['default'] = str_replace("{style_legend},", "{style_legend},tailwind,", $GLOBALS['TL_DCA']['tl_layout']['palettes']['default']);


