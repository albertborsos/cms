<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */
namespace craftunit\web;


use craft\test\mockclasses\arrayable\ExampleArrayble;
use craft\test\mockclasses\models\ExampleModel;
use craft\test\TestCase;
use craft\web\View;
use craftunit\fixtures\SitesFixture;

/**
 * Unit tests for the Url Helper class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.0
 */
class ViewTest extends TestCase
{
    public function _fixtures()
    {
        return [
            'sites' => [
                'class' => SitesFixture::class
            ]
        ];
    }

    /**
     * @var \UnitTester
     */
    protected $tester;

    public function _before()
    {
        parent::_before();

        // By default we want to be in site mode.
        \Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);
    }


    /**
     * @param $result
     * @param $input
     * @dataProvider normalizeObjectTemplateData
     */
    public function testNormalizeObjectTemplate($result, $input)
    {
        $this->assertSame($result, \Craft::$app->getView()->normalizeObjectTemplate($input));
    }
    public function normalizeObjectTemplateData()
    {
        return [
            ['{{ object.titleWithHyphens|replace({\'-\': \'!\'}) }}', '{{ object.titleWithHyphens|replace({\'-\': \'!\'}) }}'],
            ['{{ (_variables.foo ?? object.foo)|raw }}', '{foo}'],
            ['{{ (_variables.foo ?? object.foo).bar|raw }}', '{foo.bar}'],
            ['{foo : \'bar\'}', '{foo : \'bar\'}'],
            ['{{foo}}', '{{foo}}'],
            ['{% foo %}', '{% foo %}'],
            ['{{ (_variables.foo ?? object.foo).fn({bar: baz})|raw }}', '{foo.fn({bar: baz})}'],
            ['{{ (_variables.foo ?? object.foo).fn({bar: {baz: 1}})|raw }}', '{foo.fn({bar: {baz: 1}})}'],
            ['{{ (_variables.foo ?? object.foo).fn(\'bar:baz\')|raw }}', '{foo.fn(\'bar:baz\')}'],
            ['{{ (_variables.foo ?? object.foo).fn({\'bar\': baz})|raw }}', '{foo.fn({\'bar\': baz})}']
        ];
    }

    public function testDoesTemplateExistWithCustomSite()
    {
        // Ensure that the current site is the one with the testSite3 handle
        \Craft::$app->getSites()->setCurrentSite(\Craft::$app->getSites()->getSiteByHandle('testSite3'));

        $this->assertSame(
            \Craft::getAlias('@craftunittemplates/testSite3/craft.twig'),
            \Craft::$app->getView()->resolveTemplate('craft')
        );
    }
    /**
     * @param $result
     * @param $templatePath
     * @dataProvider doesTemplateExistData
     */
    public function testDoesTemplateExistsInSite($result, $templatePath, $templateMode = null)
    {
        if ($templateMode !== null) {
            \Craft::$app->getView()->setTemplateMode($templateMode);
        }

        $doesIt = \Craft::$app->getView()->resolveTemplate($templatePath);

        if ($result === false) {
            $this->assertFalse($doesIt);
        } else {
            $this->assertSame(\Craft::getAlias($result), $doesIt);
        }
    }

    public function doesTemplateExistData()
    {
        return [
            ['@craftunittemplates/index.html', ''],
            ['@craftunittemplates/template.twig', 'template'],
            [false, 'doesntExist'],
            [false, '@craftunittemplates/index.html'],
            ['@craftunittemplates/testSite3/index.twig', 'testSite3/index.twig'],
            ['@craftunittemplates/testSite3/index.twig', 'testSite3'],
            ['@craftunittemplates/testSite3/index.twig', 'testSite3/'],

            // Cp Paths
            ['@craft/templates/index.html', '', View::TEMPLATE_MODE_CP],
            ['@craft/templates/index.html', 'index', View::TEMPLATE_MODE_CP],
            ['@craft/templates/entries/index.html', 'entries', View::TEMPLATE_MODE_CP],



        ];
    }

    /**
     * @see testDoesTemplateExistsInSite
     * @param $result
     * @param $input
     * @dataProvider privateResolveTemplateData
     */
    public function testPrivateResolveTemplate($result, $basePath, $name, $templateExtensions = null, $viewTemplateNameExtensions = null)
    {
        // If the data wants to set something custom? Set it as a prop.
        if ($templateExtensions !== null) {
            $this->setInaccessibleProperty(\Craft::$app->getView(), '_defaultTemplateExtensions', $templateExtensions);
        }

        // Same with index names
        if ($viewTemplateNameExtensions !== null) {
            $this->setInaccessibleProperty(\Craft::$app->getView(), '_indexTemplateFilenames', $viewTemplateNameExtensions);
        }

        // Lets test stuff.
        $resolved = $this->resolveTemplate(\Craft::getAlias($basePath), $name);
        $this->assertSame(\Craft::getAlias($result), $resolved);
    }
    public function privateResolveTemplateData()
    {
        return [
            ['@craftunittemplates/template.twig', '@craftunittemplates', 'template'],
            ['@craftunittemplates/index.html', '@craftunittemplates', 'index'],
            ['@craftunittemplates/doubleindex/index.html', '@craftunittemplates/doubleindex', 'index'],

            // Index is found by default
            ['@craftunittemplates/index.html', '@craftunittemplates', ''],

            // Assert that registering custom extensions works.
            ['@craftunittemplates/dotxml.xml', '@craftunittemplates', 'dotxml', ['xml']],
            [null, '@craftunittemplates', 'dotxml'],
            ['@craftunittemplates/dotxml.xml', '@craftunittemplates', 'dotxml.xml',],

            // Allow change in index names
            ['@craftunittemplates/template.twig', '@craftunittemplates', '', null, ['template']],
        ];
    }

    /**
     * Test that Craft::$app->getView()->renderTemplates(); Seems to work correctly with twig. Doesnt impact global props
     * and respects passed in variables.
     * @param $result
     * @param $template
     * @param $variables
     */
    public function testRenderTemplate()
    {
        // Assert that the _renderingTemplate prop goes in and comes out as null.
        $this->assertSame(null, $this->getInaccessibleProperty(\Craft::$app->getView(), '_renderingTemplate'));

        $result = \Craft::$app->getView()->renderTemplate('withvar', ['name' => 'Giel Tettelaar']);

        $this->assertSame($result, 'Hello iam Giel Tettelaar');
        $this->assertSame(null, $this->getInaccessibleProperty(\Craft::$app->getView(), '_renderingTemplate'));

        // Test that templates can work without variables.
        $result = \Craft::$app->getView()->renderTemplate('novar');

        $this->assertSame($result, 'I have no vars');
    }

    public function testRenderMacro()
    {
        \Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);
        $result = \Craft::$app->getView()->renderTemplateMacro('macros', 'testMacro1', ['arg1' => 'Craft', 'arg2' => 'CMS']);
        $this->assertSame('Craft-CMS', $result);
    }

    public function testRenderString()
    {
        $result = \Craft::$app->getView()->renderString('{{ arg1 }}-{{ arg2 }}', ['arg1' => 'Craft', 'arg2' => 'CMS']);
        $this->assertSame('Craft-CMS', $result);
    }

    /**
     * @param $result
     * @param $template
     * @param $object
     * @param $variables
     * @dataProvider renderObjectTemplateData
     */
    public function testRenderObjectTemplate($result, $template, $object, array $variables = [])
    {
        $res = \Craft::$app->getView()->renderObjectTemplate($template, $object, $variables);
        $this->assertSame($result, $res);
    }
    public function renderObjectTemplateData()
    {
        $model = new ExampleModel();
        $model->exampleParam = 'Example Param';

        $arrayable = new ExampleArrayble();
        $arrayable->exampleArrayableParam = 'Example param';
        $arrayable->extraField = 'ExtraField';

        return [
            // No tags. Then it returns the template
            ['[[ exampleParam ]]', '[[ exampleParam ]]', $model, ['vars' => 'vars']],

            // Base arrayable test
            ['Example paramExample param', '{ exampleArrayableParam }{ object.exampleArrayableParam }', $arrayable],
            ['ExtraFieldExtraField', '{ extraField }{ object.extraField }', $arrayable],

            // Base model test
            ['Example ParamExample Param', '{{ exampleParam }}{{ object.exampleParam }}', $model],
            ['Example ParamExample Param', '{ exampleParam }{ object.exampleParam }', $model],

            // Test that model params dont overide variable params.
            ['IM DIFFERENTExample Param', '{ exampleParam }{ object.exampleParam }', $model, ['exampleParam' => 'IM DIFFERENT']],
        ];
    }

    // Helpers
    // =========================================================================

    private function resolveTemplate($basePath, $name)
    {
        return $this->invokeMethod(\Craft::$app->getView(), '_resolveTemplate', [$basePath, $name]);
    }

}
