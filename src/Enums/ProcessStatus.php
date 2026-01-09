<?php

namespace Timeax\FortiPlugin\Enums;

enum ProcessStatus: string
{
	case success = "success";
	case failed = "failed";
	case pending = "pending";
}
