const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
	entry:  path.join(__dirname, 'init.js'),
	output: {
		path: path.resolve(__dirname, '../js'),
		publicPath: '/js/',
		filename: 'notifications.js'
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader']
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader'
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/
			},
			{
				test: /\.(png|jpg|gif|svg)$/,
				loader: 'file-loader',
				options: {
					name: '[name].[ext]?[hash]'
				}
			},
			{
				test: /\.handlebars/,
				loader: "handlebars-loader",
				query: {
					extensions: '.handlebars'
				}
			}
		]
	},
	plugins: [new VueLoaderPlugin()],
	resolve: {
		alias: {
			vue$: 'vue/dist/vue.esm.js'
		},
		extensions: ['*', '.js', '.vue', '.json']
	}
};
