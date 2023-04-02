<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

class ProxyGenerator
{
    public const PROXY_NAMESPACE = "Proxies\\__HTTP__\\";

    public function __construct(private string $cachePath = '')
    {
    }

    public function generate(string $className): void
    {
        $FQCNComponents = explode('\\', $className);
        $class = array_pop($FQCNComponents);
        $classNamespace = implode('\\', $FQCNComponents);

        $proxyDir = str_replace('\\', '/', $this->cachePath . '/' . $classNamespace);
        $proxyPath = $proxyDir . '/' . $class . 'Proxy.php';

        if (file_exists($proxyPath)) {
            return;
        }

        if (!is_dir($proxyDir)) {
            mkdir($proxyDir, recursive: true);
        }

        $content = strtr($this->getTemplate(), ['{{classPath}}' => $classNamespace, '{{class}}' => $class]);

        file_put_contents($proxyPath, $content);
        @chmod($proxyPath, 0664);
    }

    public function getProxyNamespace(): string
    {
        return self::PROXY_NAMESPACE;
    }

    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    private function getTemplate(): string
    {
        return '<?php namespace Proxies\__HTTP__\{{classPath}}{class {{class}}Proxy extends \\{{classPath}}\\{{class}}'
            . '{use \Awwar\PhpHttpEntityManager\Proxy\ProxyTrait;}};';
    }
}
