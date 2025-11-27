<?php

namespace Nedev\ContaoTailwindBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Nedev\ContaoTailwindBundle\NedevContaoTailwindBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {

        return [
            BundleConfig::create(NedevContaoTailwindBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
        ];
    }
}