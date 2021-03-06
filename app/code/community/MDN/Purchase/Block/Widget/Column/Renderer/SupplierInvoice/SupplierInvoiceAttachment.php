<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2009 Maison du Logiciel (http://www.maisondulogiciel.com)
 * @author : Olivier ZIMMERMANN
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class MDN_Purchase_Block_Widget_Column_Renderer_SupplierInvoice_SupplierInvoiceAttachment
	extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract 
{
    public function render(Varien_Object $row)
    {
		$html = '';
		if($row->getpsi_attachment()){
			$imageUrl = Mage::getDesign()->getSkinUrl('images/icon_export.png');
			$url = $this->getUrl('adminhtml/Purchase_SupplierInvoice/DownloadAttachment', array('psi_id' => $row->getpsi_id()));
			$html .= '<a href="'.$url.'" alt="'.mage::helper('purchase')->__('Download').'"><img src="' . $imageUrl . '"></a>';
		}
		return $html;
    }

	public function renderExport(Varien_Object $row)
	{
		return '';
	}
    
}