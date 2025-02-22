<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

use Shopware\Core\Framework\Feature;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - will be marked internal
 */
class ChangelogDefinition
{
    /**
     * @Assert\NotBlank(
     *     message="The title should not be blank"
     * )
     */
    private string $title;

    /**
     * @Assert\NotBlank(
     *     message="The Jira ticket should not be blank"
     * )
     * @Assert\Regex(
     *     pattern="/^NEXT-\d+$/",
     *     message="The Jira ticket has an invalid format"
     * )]
     */
    private string $issue;

    private ?string $flag;

    private ?string $author;

    private ?string $authorEmail;

    private ?string $authorGitHub;

    private ?string $core;

    private ?string $storefront;

    private ?string $administration;

    private ?string $api;

    private ?string $upgrade;

    private ?string $nextMajorVersionChanges;

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context): void
    {
        if (empty($this->api) && empty($this->core) && empty($this->storefront) && empty($this->administration)) {
            $context->buildViolation('You have to define at least one change of API, Core, Administration or Storefront')
                ->addViolation();
        }

        if ($this->api && preg_match('/\n+#\s+(\w+)/', $this->api, $matches)) {
            $context->buildViolation(sprintf('You should use "___" to separate API and %s section', $matches[1]))
                ->atPath('api')
                ->addViolation();
        }

        if ($this->storefront && preg_match('/\n+#\s+(\w+)/', $this->storefront, $matches)) {
            $context->buildViolation(sprintf('You should use "___" to separate Storefront and %s section', $matches[1]))
                ->atPath('storefront')
                ->addViolation();
        }

        if ($this->administration && preg_match('/\n+#\s+(\w+)/', $this->administration, $matches)) {
            $context->buildViolation(sprintf('You should use "___" to separate Administration and %s section', $matches[1]))
                ->atPath('administration')
                ->addViolation();
        }

        if ($this->core && preg_match('/\n+#\s+(\w+)/', $this->core, $matches)) {
            $context->buildViolation(sprintf('You should use "___" to separate Core and %s section', $matches[1]))
                ->atPath('core')
                ->addViolation();
        }

        if ($this->upgrade && preg_match('/\n+#\s+(\w+)/', $this->upgrade, $matches)) {
            $context->buildViolation(sprintf('You should use "___" to separate Upgrade Information and %s section ', $matches[1]))
                ->atPath('upgrade')
                ->addViolation();
        }

        if ($this->nextMajorVersionChanges && preg_match('/\n+#\s+(\w+)/', $this->nextMajorVersionChanges, $matches)) {
            $context->buildViolation(sprintf('You should use "___" to separate Next Major Version Changes and %s section ', $matches[1]))
                ->atPath('nextMajorVersionChanges')
                ->addViolation();
        }

        if ($this->flag && !Feature::has($this->flag)) {
            $context->buildViolation(sprintf('Unknown flag %s is assigned ', $this->flag))
                ->atPath('flag')
                ->addViolation();
        }
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): ChangelogDefinition
    {
        $this->title = $title;

        return $this;
    }

    public function getIssue(): string
    {
        return $this->issue;
    }

    public function setIssue(string $issue): ChangelogDefinition
    {
        $this->issue = $issue;

        return $this;
    }

    public function getFlag(): ?string
    {
        return $this->flag;
    }

    public function setFlag(?string $flag): ChangelogDefinition
    {
        $this->flag = $flag;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): ChangelogDefinition
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(?string $authorEmail): ChangelogDefinition
    {
        $this->authorEmail = $authorEmail;

        return $this;
    }

    public function getAuthorGitHub(): ?string
    {
        return $this->authorGitHub;
    }

    public function setAuthorGitHub(?string $authorGitHub): ChangelogDefinition
    {
        $this->authorGitHub = $authorGitHub;

        return $this;
    }

    public function getCore(): ?string
    {
        return $this->core;
    }

    public function setCore(?string $core): ChangelogDefinition
    {
        $this->core = $core;

        return $this;
    }

    public function getStorefront(): ?string
    {
        return $this->storefront;
    }

    public function setStorefront(?string $storefront): ChangelogDefinition
    {
        $this->storefront = $storefront;

        return $this;
    }

    public function getAdministration(): ?string
    {
        return $this->administration;
    }

    public function setAdministration(?string $administration): ChangelogDefinition
    {
        $this->administration = $administration;

        return $this;
    }

    public function getApi(): ?string
    {
        return $this->api;
    }

    public function setApi(?string $api): ChangelogDefinition
    {
        $this->api = $api;

        return $this;
    }

    public function getUpgradeInformation(): ?string
    {
        return $this->upgrade;
    }

    public function setUpgradeInformation(?string $upgrade): ChangelogDefinition
    {
        $this->upgrade = $upgrade;

        return $this;
    }

    public function setNextMajorVersionChanges(?string $nextMajorVersionChanges): ChangelogDefinition
    {
        $this->nextMajorVersionChanges = $nextMajorVersionChanges;

        return $this;
    }

    public function getNextMajorVersionChanges(): ?string
    {
        return $this->nextMajorVersionChanges;
    }

    public function toTemplate(): string
    {
        $template = <<<EOD
---
title: $this->title
issue: $this->issue
%FEATURE_FLAG%
%AUTHOR%
%AUTHOR_EMAIL%
%AUTHOR_GITHUB%
---
# Core
*
___
# API
*
___
# Administration
*
___
# Storefront
*
___
# Upgrade Information
## Topic 1
### Topic 1a
### Topic 1b
## Topic 2
___
# Next Major Version Changes
## Breaking Change 1:
* Do this
## Breaking Change 2:
change
```
static
```
to
```
self
```
EOD;
        $template = str_replace('%FEATURE_FLAG%', ($this->flag ? 'flag: ' . $this->flag : ''), $template);
        $template = str_replace('%AUTHOR%', ($this->author ? 'author: ' . $this->author : ''), $template);
        $template = str_replace('%AUTHOR_EMAIL%', ($this->authorEmail ? 'author_email: ' . $this->authorEmail : ''), $template);
        $template = str_replace('%AUTHOR_GITHUB%', ($this->authorGitHub ? 'author_github: ' . $this->authorGitHub : ''), $template);
        $template = str_replace("\n\n", "\n", $template);

        return trim($template);
    }
}
