<?php

namespace Nedev\ContaoTailwindBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Nedev\ContaoTailwindBundle\DependencyInjection\NedevContaoTailwindExtension;

class NedevContaoTailwindBundle extends Bundle
{
    public function getContainerExtension(): NedevContaoTailwindExtension
    {
        return new NedevContaoTailwindExtension();
    }
}