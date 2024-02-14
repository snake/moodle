<?php

namespace Packback\Lti1p3;

class LtiDeepLinkResourceIframe
{
    private ?int $width;
    private ?int $height;
    private ?string $src;

    public function __construct(?int $width = null, ?int $height = null, ?string $src = null)
    {
        $this->width = $width ?? null;
        $this->height = $height ?? null;
        $this->src = $src ?? null;
    }

    public static function new(): LtiDeepLinkResourceIframe
    {
        return new LtiDeepLinkResourceIframe();
    }

    public function setWidth(?int $width): LtiDeepLinkResourceIframe
    {
        $this->width = $width;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setHeight(?int $height): LtiDeepLinkResourceIframe
    {
        $this->height = $height;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setSrc(?string $src): LtiDeepLinkResourceIframe
    {
        $this->src = $src;

        return $this;
    }

    public function getSrc(): ?string
    {
        return $this->src;
    }

    public function toArray(): array
    {
        $iframe = [];

        if (isset($this->width)) {
            $iframe['width'] = $this->width;
        }
        if (isset($this->height)) {
            $iframe['height'] = $this->height;
        }
        if (isset($this->src)) {
            $iframe['src'] = $this->src;
        }

        return $iframe;
    }
}
