<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum Install: string
{
    case BREAK = 'break';
    case INSTALL = 'install';
    case ASK = 'ask';
}
