/**
 * 文派文档评论区块（无需构建）。服务端渲染，见 src/Embed.php。
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.blockEditor ) {
		return;
	}
	var el = wp.element.createElement;
	var be = wp.blockEditor;
	var c = wp.components;

	wp.blocks.registerBlockType( 'cocobook-docs/comments', {
		apiVersion: 2,
		title: '文派文档评论',
		description: '嵌入绑定到某个 文档页的讨论区（cocobook:page:<id>）。',
		category: 'widgets',
		icon: 'admin-comments',
		keywords: [ 'cocobook', 'comments', '评论', '讨论' ],
		supports: { html: false, multiple: false },
		attributes: { page: { type: 'string', default: '' } },
		edit: function ( props ) {
			var page = props.attributes.page || '';
			return el(
				'div',
				be.useBlockProps(),
				el(
					c.Placeholder,
					{
						icon: 'admin-comments',
						label: '文派文档评论',
						instructions: page
							? '已绑定：' + page + '。前台会显示该页面的讨论区。'
							: '填写 文档页 ID 或页面链接；留空则使用本文对应的 文档页。',
					},
					el( c.TextControl, {
						label: '页面 ID 或链接',
						value: page,
						placeholder: '例如 123，或 https://docs.example.com/books/xxx/page/yyy',
						onChange: function ( v ) {
							props.setAttributes( { page: v } );
						},
						__nextHasNoMarginBottom: true,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
