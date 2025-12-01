<?php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_tailwind_settings'] = [
    'config' => [
        'dataContainer'               => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode'        => 1,
            'fields'      => ['id'],
            'panelLayout' => 'filter;search,limit',
            'disableGrouping' => true
        ],
        'label' => [
            'fields' => ['title'],
            'format' => '%s'
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title;{files_legend},input_file;{config_legend},base_font_size,breakpoints,colors,config'
    ],
    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ],
        'title' => [
            'inputType'               => 'text',
            'eval'                    => ['maxlength'=>255, 'tl_class'=>'w50'],
            'sql'                     => "varchar(255) NOT NULL default ''"
        ],
        'input_file' => [
            'inputType'               => 'fileTree',
            'eval'                    => ['multiple'=>false, 'fieldType'=>'radio', 'filesOnly'=>true, 'extensions'=>'css,scss,less', 'mandatory'=>false],
            'sql'                     => "binary(16) NULL"
        ],
        'config' => [
            'inputType'               => 'textarea',
            'eval'                    => ['tl_class'=>'clr'],
            'sql'                     => "text NULL"
        ],
        'base_font_size' => [
            'inputType'               => 'text',
            'eval'                    => ['rgxp'=>'natural', 'tl_class'=>'w50', 'mandatory'=>true],
            'sql'                     => "smallint(5) unsigned NOT NULL default 16",
        ],
        'breakpoints' => [
            'inputType'               => 'keyValueWizard',
            'eval'                    => ['keyLabel' => &$GLOBALS['TL_LANG']['tl_tailwind_settings']['breakpoints_keyLabel'], 'valueLabel' => &$GLOBALS['TL_LANG']['tl_tailwind_settings']['breakpoints_valueLabel'], 'tl_class'=>'w50 clr'],
            'sql'                     => "text NULL",
            'load_callback' => [
                function ($value, $dca) {
                    if($value == null || count(unserialize($value)) == 0) {
                        $value = serialize($GLOBALS['tailwind']['default_breakpoints']);
                    }
                    return $value;
                },
            ],
            'save_callback' => [
                function ($value, $dca) {
                    if($value == null || count(unserialize($value)) == 0) {
                        $value = $GLOBALS['tailwind']['default_breakpoints'];
                    } else {
                        $value = unserialize($value);
                        usort($value, function ($a, $b) {
                            return $a["value"] <=> $b["value"];
                        });
                    }
                    $value = serialize($value);
                    return $value;
                },
            ]
        ],
        'colors' => [
            'inputType'               => 'keyValueWizard',
            'eval'                    => ['keyLabel' => &$GLOBALS['TL_LANG']['tl_tailwind_settings']['colors_keyLabel'], 'valueLabel' => &$GLOBALS['TL_LANG']['tl_tailwind_settings']['colors_valueLabel'], 'tl_class'=>'w50'],
			'sql'                     => "text NULL"
        ]

    ]
];