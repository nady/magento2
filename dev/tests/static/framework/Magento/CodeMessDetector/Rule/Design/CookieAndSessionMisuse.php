<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\CodeMessDetector\Rule\Design;

use PDepend\Source\AST\ASTClass;
use PHPMD\AbstractNode;
use PHPMD\AbstractRule;
use PHPMD\Node\ClassNode;
use PHPMD\Rule\ClassAware;

/**
 * Session and Cookies must be used only in HTML Presentation layer.
 */
class CookieAndSessionMisuse extends AbstractRule implements ClassAware
{
    /**
     * Is given class a controller?
     *
     * @param \ReflectionClass $class
     * @return bool
     */
    private function isController(\ReflectionClass $class): bool
    {
        return $class->isSubclassOf(\Magento\Framework\App\ActionInterface::class);
    }

    /**
     * Is given class a block?
     *
     * @param \ReflectionClass $class
     * @return bool
     */
    private function isBlock(\ReflectionClass $class): bool
    {
        return $class->isSubclassOf(\Magento\Framework\View\Element\BlockInterface::class);
    }

    /**
     * Is given class an HTML UI data provider?
     *
     * @param \ReflectionClass $class
     * @return bool
     */
    private function isUiDataProvider(\ReflectionClass $class): bool
    {
        return $class->isSubclassOf(
            \Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface::class
        );
    }

    /**
     * Is given class an HTML UI Document?
     *
     * @param \ReflectionClass $class
     * @return bool
     */
    private function isUiDocument(\ReflectionClass $class): bool
    {
        return $class->isSubclassOf(\Magento\Framework\View\Element\UiComponent\DataProvider\Document::class)
            || $class->getName() === \Magento\Framework\View\Element\UiComponent\DataProvider\Document::class;
    }

    /**
     * Is given class a plugin for controllers?
     *
     * @param \ReflectionClass $class
     * @return bool
     */
    private function isControllerPlugin(\ReflectionClass $class): bool
    {
        try {
            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (preg_match('/^(after|around|before).+/i', $method->getName())) {
                    $argument = $method->getParameters()[0]->getClass();
                    $isAction = $argument->isSubclassOf(\Magento\Framework\App\ActionInterface::class)
                        || $argument->getName() === \Magento\Framework\App\ActionInterface::class;
                    if ($isAction) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * Is given class a plugin for blocks?
     *
     * @param \ReflectionClass $class
     * @return bool
     */
    private function isBlockPlugin(\ReflectionClass $class): bool
    {
        try {
            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (preg_match('/^(after|around|before).+/i', $method->getName())) {
                    $argument = $method->getParameters()[0]->getClass();
                    $isBlock = $argument->isSubclassOf(\Magento\Framework\View\Element\BlockInterface::class)
                        || $argument->getName() === \Magento\Framework\View\Element\BlockInterface::class;
                    if ($isBlock) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * Whether given class depends on classes to pay attention to.
     *
     * @param \ReflectionClass $class
     * @return bool
     */
    private function doesUseRestrictedClasses(\ReflectionClass $class): bool
    {
        $constructor = $class->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $argument) {
                if ($class = $argument->getClass()) {
                    if ($class->isSubclassOf(\Magento\Framework\Session\SessionManagerInterface::class)
                        || $class->getName() === \Magento\Framework\Session\SessionManagerInterface::class
                        || $class->isSubclassOf(\Magento\Framework\Stdlib\Cookie\CookieReaderInterface::class)
                        || $class->getName() === \Magento\Framework\Stdlib\Cookie\CookieReaderInterface::class
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     *
     * @param ClassNode|ASTClass $node
     */
    public function apply(AbstractNode $node)
    {
        try {
            $class = new \ReflectionClass($node->getFullQualifiedName());
        } catch (\Throwable $exception) {
            //Failed to load class, nothing we can do
            return;
        }

        if ($this->doesUseRestrictedClasses($class)) {
            if (!$this->isController($class)
                && !$this->isBlock($class)
                && !$this->isUiDataProvider($class)
                && !$this->isUiDocument($class)
                && !$this->isControllerPlugin($class)
                && !$this->isBlockPlugin($class)
            ) {
                $this->addViolation($node, [$node->getFullQualifiedName()]);
            }
        }
    }
}
