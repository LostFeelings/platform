<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @package core
 *
 * @internal
 *
 * @extends Collection<RequirementCheck>
 */
class RequirementsCheckCollection extends Collection
{
    public function getExpectedClass(): ?string
    {
        return RequirementCheck::class;
    }

    public function getPathChecks(): self
    {
        return $this->filterInstance(PathCheck::class);
    }

    public function getSystemChecks(): self
    {
        return $this->filterInstance(SystemCheck::class);
    }

    public function hasError(): bool
    {
        return $this->filter(static function (RequirementCheck $check): bool {
            return $check->getStatus() === RequirementCheck::STATUS_ERROR;
        })->first() !== null;
    }

    public function hasPathError(): bool
    {
        return $this->getPathChecks()->hasError();
    }

    public function hasSystemError(): bool
    {
        return $this->getSystemChecks()->hasError();
    }
}
