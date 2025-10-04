<?php

namespace Timeax\FortiPlugin\Enums;

enum AuthorRole: string
{
	case owner = "owner";
	case maintainer = "maintainer";
	case contributor = "contributor";
}
