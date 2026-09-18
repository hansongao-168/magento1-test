<?php

/**
 * XFE_Demo_Model_Service_Greeter
 *
 * 教学示例 service 1:返回一个 greeting 字符串。
 *
 * 注意:本类没有任何 XFE_* 依赖,纯业务方法。
 * 它的存在完全由 injection.xml 声明,
 * 被任何声明了 hook_demo_user_login 的 calling 触发。
 */
class XFE_Demo_Model_Service_Greeter
{
    /**
     * @param string $name 用户名
     * @return string 欢迎语
     */
    public function greet($name)
    {
        return 'Hello, ' . $name . '!';
    }
}
