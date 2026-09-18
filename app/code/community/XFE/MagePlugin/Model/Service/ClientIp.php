<?php
/**
 * XFE_MagePlugin 客户端 IP 解析服务（L3 Service）。
 *
 * 目的：统一、稳定地获取后台客户端真实 IP。
 *
 * 背景：Magento 默认 Mage::app()->getRequest()->getClientIp() 的取 IP 优先级是
 *   HTTP_CLIENT_IP → HTTP_X_FORWARDED_FOR → REMOTE_ADDR，且对 X-Forwarded-For
 *   不解析逗号分隔的多 IP 串。在多级代理 / CDN / 负载均衡环境下，这几个头可能
 *   由不同节点追加或修改，导致同一请求多次取值不一致（用户反馈「有时获取不同 IP」）。
 *
 * 本服务固定解析规则，保证对同一请求返回一致的 IP：
 *   1. HTTP_X_REAL_IP（Nginx 设置的单个真实 IP，最精确，优先取）。
 *   2. HTTP_X_FORWARDED_FOR 取最左边的**第一个公网 IP**（最接近真实客户端）。
 *   3. HTTP_CLIENT_IP、REMOTE_ADDR 作为回退。
 *   4. 过滤私有 / 保留 IP 段；若均为私有 IP（纯内网环境），回退第一个有效候选。
 *
 * 注意：HTTP_X_REAL_IP / HTTP_X_FORWARDED_FOR 均可被客户端伪造，
 *   需在前端 Nginx / 代理层正确设置并覆盖，才保证可信。
 */
class XFE_MagePlugin_Model_Service_ClientIp
{
    /**
     * 获取客户端 IP。
     *
     * @return string
     */
    public function getClientIp()
    {
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = Mage::app()->getRequest();

        $candidates = array();

        // 1. X-Real-IP：Nginx 设置的单个真实 IP，最精确
        $realIp = $request->getServer('HTTP_X_REAL_IP');
        if ($realIp) {
            $candidates[] = trim($realIp);
        }

        // 2. X-Forwarded-For：可逗号分隔多 IP，取第一个公网 IP
        $xff = $request->getServer('HTTP_X_FORWARDED_FOR');
        if ($xff) {
            foreach (explode(',', $xff) as $ip) {
                $candidates[] = trim($ip);
            }
        }

        // 3. Client-IP
        $clientIp = $request->getServer('HTTP_CLIENT_IP');
        if ($clientIp) {
            $candidates[] = trim($clientIp);
        }

        // 4. Remote-Addr（最可靠回退）
        $remoteAddr = $request->getServer('REMOTE_ADDR');
        if ($remoteAddr) {
            $candidates[] = trim($remoteAddr);
        }

        return $this->resolve($candidates);
    }

    /**
     * 从候选 IP 列表中解析出最终 IP（纯逻辑，可测试）。
     *
     * 规则：返回第一个「非私有」IP；若全部为私有/无效，则回退第一个有效 IP。
     *
     * @param array $candidates
     * @return string
     */
    public function resolve(array $candidates)
    {
        $fallback = '';
        foreach ($candidates as $ip) {
            $ip = $this->_normalize($ip);
            if (!$ip) {
                continue;
            }
            if ($fallback === '') {
                $fallback = $ip;
            }
            if (!$this->_isPrivateIp($ip)) {
                return $ip;
            }
        }
        return $fallback;
    }

    /**
     * 规范化 IP 字符串（去除端口、IPv4-mapped IPv6 前缀等）。
     *
     * @param string $ip
     * @return string
     */
    protected function _normalize($ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '') {
            return '';
        }
        // 去除 IPv4-mapped IPv6 前缀，如 ::ffff:1.2.3.4
        if (stripos($ip, '::ffff:') === 0) {
            $ip = substr($ip, 7);
        }
        // 去除端口号（ip:port 形式）
        if (strpos($ip, ':') !== false && substr_count($ip, ':') === 1) {
            list($ip) = explode(':', $ip);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * 判断 IP 是否为私有 / 保留地址。
     *
     * @param string $ip
     * @return bool
     */
    protected function _isPrivateIp($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
