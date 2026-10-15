<?php

namespace Tests\Support;

trait ReadsMarketingStyles
{
    /** Read only the stylesheets actually referenced by this view/response. */
    protected function withLinkedMarketingStyles(string $markup): string
    {
        preg_match_all('#css/marketing/([a-z0-9-]+\.css)#', $markup, $matches);
        foreach (array_unique($matches[1]) as $name) {
            $path = public_path('css/marketing/'.$name);
            $this->assertFileExists($path);
            $markup .= "\n".file_get_contents($path);
        }

        return $markup;
    }
}
