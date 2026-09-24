const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
  const isProd = argv.mode === 'production';

  return {
    entry: './src/index.js',
    output: {
      path: path.resolve(__dirname, 'dist'),
      filename: isProd ? 'assets/js/[name].[contenthash:8].js' : 'assets/js/[name].js',
      publicPath: '',
      clean: true,
    },
    devtool: isProd ? false : 'eval-source-map',
    module: {
      rules: [
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: [['@babel/preset-env', { targets: '> 0.5%, last 2 versions, not dead' }]],
            },
          },
        },
        {
          test: /\.(scss|sass)$/,
          use: [
            isProd ? MiniCssExtractPlugin.loader : 'style-loader',
            'css-loader',
            {
              loader: 'sass-loader',
              options: { api: 'modern-compiler' },
            },
          ],
        },
        {
          test: /\.(png|jpg|jpeg|gif|svg|webp)$/,
          type: 'asset',
          parser: { dataUrlCondition: { maxSize: 8 * 1024 } },
          generator: { filename: 'assets/img/[name].[hash:8][ext]' },
        },
      ],
    },
    plugins: [
      new HtmlWebpackPlugin({
        template: './src/index.html',
        favicon: false,
      }),
      ...(isProd
        ? [new MiniCssExtractPlugin({ filename: 'assets/css/[name].[contenthash:8].css' })]
        : []),
    ],
    devServer: {
      static: { directory: path.resolve(__dirname, 'dist') },
      port: 8081,
      hot: true,
      open: false,
      historyApiFallback: true,
    },
    performance: { hints: false },
  };
};
