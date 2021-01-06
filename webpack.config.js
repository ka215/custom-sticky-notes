const path = require('path')
const glob = require('glob')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const autoprefixer = require('autoprefixer')

const srcDir = './src'
const entryObj = {
    'js/csnp' : [ '@babel/polyfill', path.resolve(__dirname, srcDir, 'scripts', 'index.js') ],
    'css/csnp': [ path.resolve(__dirname, srcDir, 'styles', 'index.scss') ]
}

const isDevelopment = process.env.NODE_ENV === 'development'

module.exports = {
    mode: isDevelopment ? 'development' : 'production',
    devtool: isDevelopment ? 'inline-source-map' : false,
    entry: entryObj,
    output: {
        path: path.join(__dirname, 'assets'),
        filename: "[name].js"
    },
    module: {
        rules: [
            {
                enforce: 'pre',
                test: /\.js$/,
                exclude: /(node_modules|assets|views)/,
                loader: 'eslint-loader',
                options: {
                    fix: true
                }
            },
            {
                test: /\.js$/,
                exclude: /(node_modules|assets|views)/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env']
                    }
                }
            },
            {
                test: /\.(sa|sc|c)ss$/,
                exclude: /(node_modules|assets|views)/,
                use: [
                    // During development, CSS is bundled with JS, and it is divided and output for production.
                    // isDevelopment ? 'style-loader' : MiniCssExtractPlugin.loader,
                    // Always split output of JS and CSS
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            // During development, CSS is bundled with JS, and it is divided and output for production.
                            // url: isDevelopment ? true : false,
                            url: false,
                            sourceMap: isDevelopment ? true : false,
                            // 2 => postcss-loader, sass-loader
                            //importLoaders: 2
                        }
                    },
                    {
                        loader: 'postcss-loader',
                        options: {
                            sourceMap: isDevelopment ? true : false,
                            postcssOptions: {
                                plugins: () => [ autoprefixer({ grid: true }) ]
                            }
                        }
                    },
                    {
                        loader: 'sass-loader',
                        options: {
                            sourceMap: isDevelopment ? true : false
                        }
                    }
                ]
            }
        ]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: './[name].css',
            chunkFilename: '[id].css'
        }),
    ],
    watch: isDevelopment ? true : false,
    watchOptions: {
        ignored: [ 'assets', 'views', 'vendor', 'node_modules' ]
    }
};