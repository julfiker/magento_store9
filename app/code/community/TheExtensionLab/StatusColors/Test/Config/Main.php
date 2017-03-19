<?php

class TheExtensionLab_StatusColors_Test_Config_Main extends EcomDev_PHPUnit_Test_Case_Config
{

    public function testSetupResources()
    {
        $this->assertSetupResourceDefined();
        $this->assertSetupResourceExists();
    }

    public function testClassAliases()
    {
        $this->assertModelAlias('theextensionlab_statuscolors/observer','TheExtensionLab_StatusColors_Model_Observer');
        $this->assertResourceModelAlias('theextensionlab_statuscolors/setup','TheExtensionLab_StatusColors_Model_Resource_Setup');
        $this->assertHelperAlias('theextensionlab_statuscolors','TheExtensionLab_StatusColors_Helper_Data');
    }

    public function testLayoutFiles()
    {
        $this->assertLayoutFileDefined('adminhtml','theextensionlab/statuscolors.xml');
        $this->assertLayoutFileExists('adminhtml','theextensionlab/statuscolors.xml');
    }

    public function testObserverConfig()
    {
        $this->assertEventObserverDefined(
            'adminhtml',
            'adminhtml_block_html_before',
            'TheExtensionLab_StatusColors_Model_Observer',
            'adminhtmlBlockHtmlBefore'
        );

        $this->assertEventObserverDefined(
            'adminhtml',
            'core_block_abstract_to_html_after',
            'TheExtensionLab_StatusColors_Model_Observer',
            'coreBlockAbstractToHtmlAfter'
        );
    }

}