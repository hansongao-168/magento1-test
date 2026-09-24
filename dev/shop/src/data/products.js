// Mock product catalog. Images use deterministic placeholders (picsum) so the
// page renders without bundling binary assets; replace with real CDN URLs in
// production. The `color` field drives the SVG fallback used while loading.
const products = [
  {
    id: 'p-001',
    name: '无线蓝牙耳机 Pro',
    category: 'audio',
    price: 599,
    originalPrice: 799,
    rating: 4.7,
    stock: 24,
    image: 'https://picsum.photos/seed/headphones/640/480',
    color: '#3b82f6',
    summary: '主动降噪 · 40h 续航 · Hi-Fi 级音质',
    description:
      '采用混合主动降噪技术,搭载 40mm 大动圈单元,支持 LDAC 高码率传输。' +
      '柔软记忆海绵耳罩配合可调节头梁,长时间佩戴依旧舒适。',
  },
  {
    id: 'p-002',
    name: '机械键盘 87 键',
    category: 'computer',
    price: 459,
    originalPrice: 599,
    rating: 4.8,
    stock: 56,
    image: 'https://picsum.photos/seed/keyboard/640/480',
    color: '#10b981',
    summary: '红轴 · RGB 背光 · 热插拔',
    description:
      'TKL 紧凑布局,87 键配备 Cherry MX 红轴,全键无冲支持热插拔更换。' +
      'PBT 双色注塑键帽,经久耐磨不打油。',
  },
  {
    id: 'p-003',
    name: '4K 显示器 27 寸',
    category: 'computer',
    price: 1899,
    originalPrice: 2299,
    rating: 4.6,
    stock: 12,
    image: 'https://picsum.photos/seed/monitor/640/480',
    color: '#8b5cf6',
    summary: 'IPS 面板 · 99% sRGB · Type-C 65W',
    description:
      '27 英寸 4K UHD IPS 显示屏,出厂校色 ΔE<2,通过 DisplayHDR 400 认证。' +
      '配备 Type-C、HDMI、DP 多接口,一线连接笔记本。',
  },
  {
    id: 'p-004',
    name: '便携咖啡杯 350ml',
    category: 'lifestyle',
    price: 89,
    originalPrice: 129,
    rating: 4.5,
    stock: 120,
    image: 'https://picsum.photos/seed/cup/640/480',
    color: '#f59e0b',
    summary: '316 不锈钢 · 12h 保温 · 轻量便携',
    description:
      '食品级 316 不锈钢内胆,真空双层结构,保冷保热 12 小时以上。' +
      '广口设计便于清洗,可单手操作一键开盖。',
  },
  {
    id: 'p-005',
    name: '智能手环 7',
    category: 'wearable',
    price: 269,
    originalPrice: 349,
    rating: 4.4,
    stock: 88,
    image: 'https://picsum.photos/seed/band/640/480',
    color: '#ef4444',
    summary: '血氧监测 · 14 天续航 · 5ATM 防水',
    description:
      '1.62 英寸 AMOLED 全面屏,支持心率、血氧、睡眠全天候监测。' +
      '5ATM 防水等级,游泳可佩戴,续航长达 14 天。',
  },
  {
    id: 'p-006',
    name: '人体工学椅',
    category: 'lifestyle',
    price: 1299,
    originalPrice: 1799,
    rating: 4.6,
    stock: 18,
    image: 'https://picsum.photos/seed/chair/640/480',
    color: '#0ea5e9',
    summary: '腰托自适应 · 4D 扶手 · 网布透气',
    description:
      '自适应腰托动态贴合脊柱曲线,4D 多向扶手满足不同坐姿需求。' +
      '高弹性网布椅面,久坐不闷,3 级气压棒安全保障。',
  },
  {
    id: 'p-007',
    name: '便携投影仪',
    category: 'audio',
    price: 2199,
    originalPrice: 2699,
    rating: 4.5,
    stock: 7,
    image: 'https://picsum.photos/seed/projector/640/480',
    color: '#a855f7',
    summary: '1080P · 自动对焦 · 内置电池',
    description:
      '原生 1080P 全高清分辨率,支持自动对焦与梯形校正。' +
      '内置大容量电池,无线缆播放电影约 2.5 小时。',
  },
  {
    id: 'p-008',
    name: '旅行双肩包 28L',
    category: 'lifestyle',
    price: 399,
    originalPrice: 499,
    rating: 4.7,
    stock: 42,
    image: 'https://picsum.photos/seed/bag/640/480',
    color: '#22c55e',
    summary: '防泼水 · 独立电脑仓 · 28L 大容量',
    description:
      '900D 牛津布防泼水面料,可容纳 16 寸笔记本的独立电脑仓。' +
      '人体工学背负系统,长时出行依旧轻松。',
  },
];

export const categories = [
  { id: 'all', name: '全部' },
  { id: 'audio', name: '影音' },
  { id: 'computer', name: '电脑配件' },
  { id: 'wearable', name: '穿戴' },
  { id: 'lifestyle', name: '生活' },
];

export function getProductById(id) {
  return products.find((p) => p.id === id) || null;
}

export function getProducts({ category = 'all', keyword = '' } = {}) {
  const kw = keyword.trim().toLowerCase();
  return products.filter((p) => {
    const matchCat = category === 'all' || p.category === category;
    const matchKw = !kw || p.name.toLowerCase().includes(kw) || p.summary.toLowerCase().includes(kw);
    return matchCat && matchKw;
  });
}

export default products;
