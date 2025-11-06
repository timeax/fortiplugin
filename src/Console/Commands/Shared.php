<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Closure;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

trait Shared
{
    protected function initializeShared(): Closure
    {
        /** @var mixed $output */
        $output = $this->output;
        if (method_exists($output, 'getOutput')) {
            $output = $output->getOutput();
        }
        $supportsSections = $output instanceof ConsoleOutputInterface;
        $sections = [];
        $progress = null;
        $filesStarted = false;

        return function (array $e) use (&$sections, &$progress, &$filesStarted, $output, $supportsSections) {
            $title = (string)($e['title'] ?? 'Scan');
            $desc = (string)($e['description'] ?? '');
            $file = (string)($e['stats']['filePath'] ?? '');
            // $size = $e['stats']['size'] ?? null;

            // Light-weight UI: one-liners per phase + progress bar during files
            if ($title === 'Scan: File') {
                if (!$filesStarted) {
                    if ($supportsSections) {
                        if (!isset($sections['progress'])) {
                            $sections['progress'] = $output->section();
                        }
                        $progress = new ProgressBar($sections['progress'], 0);
                    } else {
                        $progress = $this->output->createProgressBar();
                    }
                    $progress->start();
                    $filesStarted = true;
                }
                if ($supportsSections) {
                    if (!isset($sections['files'])) {
                        $sections['files'] = $output->section();
                    }
                    $sections['files']->overwrite("Scanning: <info>" . basename($file) . "</info>");
                } else {
                    $this->line("Scanning: " . basename($file));
                }
                if ($progress) $progress->advance();
                return;
            }

            $msg = $desc ?: $title;
            if ($supportsSections) {
                if (!isset($sections[$title])) {
                    $sections[$title] = $output->section();
                }
                $sections[$title]->overwrite($msg);
            } else {
                $this->line($msg);
            }
        };
    }
}