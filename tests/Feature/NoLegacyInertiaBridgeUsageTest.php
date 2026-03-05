<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoLegacyInertiaBridgeUsageTest extends TestCase
{
    #[Test]
    public function legacy_html_page_inertia_components_are_not_referenced_in_source(): void
    {
        $roots = [
            app_path(),
            base_path('routes'),
            resource_path('js'),
        ];

        $patterns = [
            'Admin/Legacy/HtmlPage',
            'Client/Legacy/HtmlPage',
            'Employee/Legacy/HtmlPage',
            'Rep/Legacy/HtmlPage',
            'Support/Legacy/HtmlPage',
            'LegacyHtmlPage',
        ];

        $extensions = ['php', 'js', 'jsx', 'ts', 'tsx', 'mjs'];
        $matches = [];

        foreach ($roots as $root) {
            if (! File::exists($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                $extension = strtolower($file->getExtension());
                if (! in_array($extension, $extensions, true)) {
                    continue;
                }

                $contents = File::get($file->getPathname());
                foreach ($patterns as $needle) {
                    if (str_contains($contents, $needle)) {
                        $matches[] = [
                            'file' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                            'needle' => $needle,
                        ];
                    }
                }
            }
        }

        $this->assertSame([], $matches, 'Legacy Inertia bridge references found in source.');
    }
}

