<?php
/**
 * Admin Client Grid - "Show Secret" column renderer
 *
 * Renders a button that fetches the client secret via AJAX
 * (admin/xfeoauth2_client/reveal) and displays it in a prompt dialog
 * so it can be copied. The secret is recovered from the reversible
 * client_secret_encrypted column; clients created before 1.0.2 have no
 * recoverable copy and the server returns a 409 with a message.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_Block_Adminhtml_Client_Grid_Renderer_Secret
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $helper = Mage::helper('xfeoauth2');

        // Build the URL once. We need it in two forms:
        //  - JS string literal in onclick, where the surrounding attribute is
        //    double-quoted so we use single-quotes inside the JS and escape
        //    any ' or \ characters in the URL.
        //  - JSON-encoded bodies for the prompt/error message arguments.
        // The original code put a json_encode()'d (double-quoted) URL inside a
        //    double-quoted HTML attribute, which made the browser terminate
        //    the attribute at the first inner " and produced a syntax error in
        //    the onclick handler. Single-quoting inside onclick fixes that.
        $url = $this->getUrl('*/*/reveal', array('id' => $row->getClientId()));
        $urlJs = str_replace(
            array('\\',  "'",  '/'),
            array('\\\\', "\\'", '\\/'),
            $url
        );
        $promptTitle = json_encode($helper->__('Client Secret (copy it now)'));
        $errMsg      = json_encode($helper->__('An error occurred while retrieving the secret.'));

        // The renderer is invoked once per grid row. We emit a script block
        // that defines `window.xfeOauth2RevealSecret` exactly once (guarded
        // by a typeof check), then reference it from each button's onclick.
        // Assigning to `window.` keeps the handler reachable even in stricter
        // scoping contexts and avoids relying on hoisted function declarations.
        $html  = '<script type="text/javascript">';
        $html .= 'if (typeof window.xfeOauth2RevealSecret === "undefined") {';
        $html .= 'window.xfeOauth2RevealSecret = function (url) {';
        $html .= 'var showErr = function (msg) { alert(msg); };';
        $html .= 'new Ajax.Request(url, {';
        $html .= 'method: "get",';
        $html .= 'onSuccess: function (r) {';
        $html .= 'var d = r.responseJSON;';
        $html .= 'if (!d && typeof r.responseText === "string") {';
        $html .= 'try { d = JSON.parse(r.responseText); } catch (e) { d = null; }';
        $html .= '}';
        $html .= 'if (d && d.data && d.data.client_secret) {';
        $html .= 'window.prompt(' . $promptTitle . ', d.data.client_secret);';
        $html .= '} else {';
        $html .= 'showErr((d && d.message) ? d.message : ' . $errMsg . ');';
        $html .= '}';
        $html .= '},';
        $html .= 'onFailure: function (r) {';
        $html .= 'var d = r.responseJSON;';
        $html .= 'if (!d && typeof r.responseText === "string") {';
        $html .= 'try { d = JSON.parse(r.responseText); } catch (e) { d = null; }';
        $html .= '}';
        $html .= 'showErr((d && d.message) ? d.message : ' . $errMsg . ');';
        $html .= '}';
        $html .= '});';
        $html .= '};';
        $html .= '}';
        $html .= '</script>';

        $html .= '<button type="button" onclick="window.xfeOauth2RevealSecret(\'' . $urlJs . '\')">'
            . $helper->__('Show Secret')
            . '</button>';

        return $html;
    }
}