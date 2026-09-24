<?php
/**
 * XFE_Carrier 规则无匹配异常
 *
 * 当 OrderRuleResolver / QuoteRuleResolver 在规则集合中未找到匹配项时抛出。
 * 前端应捕获此异常并停止输出 logo 或账号。
 */
class XFE_Carrier_Exception_NoRuleMatch extends Mage_Core_Exception
{
}