<?php

declare(strict_types=1);

/**
 * Derafu: Value - Typed Value Validation and Conversion.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsValue;

use Derafu\Translation\TranslatorFactory;
use Derafu\Value\Exception\TypeMismatchException;
use Derafu\Value\Exception\ValueConversionException;
use Derafu\Value\Translation\ValueTranslationResourceProvider;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Imports the actual package configuration and checks its provider both as a
 * service and through the translator of a consuming application.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/resources/config')))
            ->load('value-services.yaml');

        return $container;
    }

    private function compile(ContainerBuilder $container): void
    {
        // Keep package services reachable so unused-private-service removal
        // cannot hide an invalid definition from the construction checks.
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }
        $container->compile();
    }

    private function consumerTranslator(): Translator
    {
        $container = $this->loadContainer();
        // The application chooses its own translator and locale. The package
        // only contributes its resource provider through the declared tag.
        $container->register('app.translator', Translator::class)
            ->setFactory([TranslatorFactory::class, 'create'])
            ->setArguments(['es', ['en'], new TaggedIteratorArgument('derafu_translation.resource_provider')]);
        $this->compile($container);
        $translator = $container->get('app.translator');
        self::assertInstanceOf(Translator::class, $translator);

        return $translator;
    }

    public function testEveryConfiguredServiceCanBeConstructed(): void
    {
        $container = $this->loadContainer();
        $this->compile($container);
        self::assertInstanceOf(ValueTranslationResourceProvider::class, $container->get(ValueTranslationResourceProvider::class));
    }

    public function testTheTaggedProviderExposesThePackageCatalogue(): void
    {
        $container = $this->loadContainer();
        self::assertArrayHasKey(
            ValueTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $this->compile($container);
        $provider = $container->get(ValueTranslationResourceProvider::class);
        self::assertInstanceOf(ValueTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        self::assertCount(1, $directories);
        self::assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
        self::assertFileExists($directories[0] . '/errors+intl-icu.es.php');
    }

    public function testTaggedResourcesTranslateARealTypeMismatchWithItsContext(): void
    {
        $translator = $this->consumerTranslator();
        try {
            ValueType::int()->parse('12', 'quantity', 'request');
            self::fail('A string was accepted as an integer.');
        } catch (TypeMismatchException $exception) {
            self::assertSame(
                'El valor en "quantity" de request debe ser de tipo int, se entregó string.',
                $exception->trans($translator)
            );
        }
    }

    public function testTaggedResourcesTranslateARealConversionError(): void
    {
        $translator = $this->consumerTranslator();
        try {
            ValueCaster::toInt('not-a-number');
            self::fail('Non-numeric text was converted to an integer.');
        } catch (ValueConversionException $exception) {
            self::assertSame('Un valor de tipo string no se puede convertir a int.', $exception->trans($translator));
        }
    }
}
