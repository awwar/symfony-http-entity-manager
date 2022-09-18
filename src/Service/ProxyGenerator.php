<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

class ProxyGenerator
{
    public const PROXY_NAMESPACE = "Proxies\\__HTTP__\\";
    private string $templateName = '<?php
namespace Proxies\__HTTP__\{{classPath}};

class {{class}}Proxy extends \\{{classPath}}\\{{class}}
{
    use \Awwar\PhpHttpEntityManager\Proxy\ProxyTrait;
}
';

    public function __construct(private string $cachePath = '')
    {
    }

    public function generate(string $className): void
    {
        $fqcnComponents = explode('\\', $className);
        $class = array_pop($fqcnComponents);
        $classNamespace = implode('\\', $fqcnComponents);

        $proxyDir = str_replace('\\', '/', $this->cachePath . '/' . $classNamespace);
        $proxyPath = $proxyDir . '/' . $class . 'Proxy.php';

        if (file_exists($proxyPath)) {
            return;
        }

        if (!is_dir($proxyDir)) {
            mkdir($proxyDir, permissions: 0777, recursive: true);
        }

        $content = strtr($this->templateName, ['{{classPath}}' => $classNamespace, '{{class}}' => $class]);

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
}
