<?php declare(strict_types=1);

namespace Shopware\Administration\DependencyInjection;

use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AdministrationMigrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $migrationPath = \dirname(__DIR__) . '/Migration';

        // configure migration directories
        $migrationSourceV4 = $container->getDefinition(MigrationSource::class . '.core.V6_4');
        $migrationSourceV4->addMethodCall('addDirectory', [$migrationPath . '/V6_4', 'Shopware\Administration\Migration\V6_4']);

        $majors = ['6_5', '6_6'];
        foreach ($majors as $major) {
            $migrationPathV5 = $migrationPath . '/' . $major;

            if (\is_dir($migrationPathV5)) {
                $migrationSource = $container->getDefinition(MigrationSource::class . '.core.V' . $major);
                $migrationSource->addMethodCall('addDirectory', [$migrationPathV5, 'Shopware\Administration\Migration\V' . $major]);
            }
        }
    }
}
