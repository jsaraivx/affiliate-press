<?php
/**
 * Plugin Name: Affiliate Press Plugin 
 * Description: Plugin para exibir quantas vendas foram feitas por cupom de desconto, e gerar relatórios individuais por parceiro.
 * Version: 0.0.1
 * Author: João Saraiva // jsaraivx
 * Author URI: http://github.com/jsaraivx
 * License: Commercial use.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Função para exibir o relatório de cupons por parceiro
function custom_woocommerce_coupon_partner_report( $atts ) {
	if ( ! isset( $_GET['coupon'] ) ) {
		return 'Cupom não especificado.';
	}

	$coupon_code = sanitize_text_field( $_GET['coupon'] );
	$args = array(
		'status' => array('wc-completed', 'wc-processing'),
		'limit' => -1, // sem limite de pedidos
	);
	$orders = wc_get_orders( $args );

	$coupon_counts = 0;
	$product_names = array();

	foreach ( $orders as $order ) {
		$used_coupons = $order->get_used_coupons();

		if ( in_array( $coupon_code, $used_coupons ) ) {
			$coupon_counts++;
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				$product_names[] = $product ? $product->get_name() : '';
			}
		}
	}

	// Exibe o relatório
	$output = '<h2>Relatório de Vendas por Cupom</h2>';
	$output .= '<p><strong>Cupom:</strong> ' . esc_html( $coupon_code ) . '</p>';
	$output .= '<p><strong>Vendas:</strong> ' . esc_html( $coupon_counts ) . '</p>';
	$output .= '<p><strong>Produtos Vendidos:</strong></p>';
	$output .= '<ul>';
	foreach ( $product_names as $product_name ) {
		$output .= '<li>' . esc_html( $product_name ) . '</li>';
	}
	$output .= '</ul>';

	return $output;
}
add_shortcode( 'partner_coupon_report', 'custom_woocommerce_coupon_partner_report' );

// Função para criar links específicos para parceiros
function custom_woocommerce_create_partner_links() {
	$partners = get_option( 'custom_woocommerce_partners', array() );

	echo '<h2>Links de Relatórios para Parceiros</h2>';
	echo '<ul>';
	foreach ( $partners as $partner_name => $details ) {
		$link = get_permalink( $details['page_id'] );
		echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( $partner_name ) . '</a></li>';
	}
	echo '</ul>';

	// Formulário para adicionar novo parceiro
	echo '<h2>Adicionar Novo Parceiro</h2>';
	echo '<form method="post" action="">';
	echo '<p><label for="partner_name">Nome do Parceiro:</label><br>';
	echo '<input type="text" name="partner_name" id="partner_name" required></p>';
	echo '<p><label for="coupon_code">Código do Cupom:</label><br>';
	echo '<input type="text" name="coupon_code" id="coupon_code" required></p>';
	echo '<p><input type="submit" name="add_partner" value="Adicionar Parceiro"></p>';
	echo wp_nonce_field( 'add_partner_action', 'add_partner_nonce' );
	echo '</form>';

	// Formulário para remover parceiro
	echo '<h2>Remover Parceiro</h2>';
	echo '<form method="post" action="">';
	echo '<p><label for="remove_coupon_code">Código do Cupom:</label><br>';
	echo '<input type="text" name="remove_coupon_code" id="remove_coupon_code" required></p>';
	echo '<p><input type="submit" name="remove_partner" value="Remover Parceiro"></p>';
	echo wp_nonce_field( 'remove_partner_action', 'remove_partner_nonce' );
	echo '</form>';
}

// Função para criar uma página de relatório para um parceiro
function custom_woocommerce_create_partner_page( $partner_name, $coupon_code ) {
	$page_id = wp_insert_post( array(
		'post_title'    => 'Relatório de ' . $partner_name,
		'post_content'  => '[partner_coupon_report]',
		'post_status'   => 'publish',
		'post_type'     => 'page',
		'meta_input'    => array(
			'partner_coupon' => $coupon_code,
		),
	) );

	return $page_id;
}

// Função para processar adição de parceiro
function custom_woocommerce_process_add_partner() {
	if ( isset( $_POST['add_partner'] ) && check_admin_referer( 'add_partner_action', 'add_partner_nonce' ) ) {
		$partners = get_option( 'custom_woocommerce_partners', array() );
		$partner_name = sanitize_text_field( $_POST['partner_name'] );
		$coupon_code = sanitize_text_field( $_POST['coupon_code'] );

		// Cria uma página de relatório para o novo parceiro
		$page_id = custom_woocommerce_create_partner_page( $partner_name, $coupon_code );

		$partners[ $partner_name ] = array(
			'coupon_code' => $coupon_code,
			'page_id' => $page_id,
		);
		update_option( 'custom_woocommerce_partners', $partners );
	}
}
add_action( 'admin_init', 'custom_woocommerce_process_add_partner' );

// Função para processar remoção de parceiro
function custom_woocommerce_process_remove_partner() {
	if ( isset( $_POST['remove_partner'] ) && check_admin_referer( 'remove_partner_action', 'remove_partner_nonce' ) ) {
		$partners = get_option( 'custom_woocommerce_partners', array() );
		$coupon_code = sanitize_text_field( $_POST['remove_coupon_code'] );

		foreach ( $partners as $partner_name => $details ) {
			if ( $details['coupon_code'] === $coupon_code ) {
				// Remove a página de relatório do parceiro
				wp_delete_post( $details['page_id'], true );
				unset( $partners[ $partner_name ] );
			}
		}
		update_option( 'custom_woocommerce_partners', $partners );
	}
}
add_action( 'admin_init', 'custom_woocommerce_process_remove_partner' );

// Adiciona a página de links ao menu do admin
function custom_woocommerce_add_links_menu() {
	add_menu_page(
		'Links de Relatórios', // Título da página
		'Links de Relatórios', // Título do menu
		'manage_options', // Capacidade
		'partner-links', // Slug do menu
		'custom_woocommerce_create_partner_links' // Função de callback
	);
}
add_action( 'admin_menu', 'custom_woocommerce_add_links_menu' );

// Filtra a página de relatório para exibir apenas dados do cupom específico
function custom_woocommerce_filter_report_content( $content ) {
	if ( is_page() && get_post_meta( get_the_ID(), 'partner_coupon', true ) ) {
		$coupon_code = get_post_meta( get_the_ID(), 'partner_coupon', true );
		$_GET['coupon'] = $coupon_code;
		$content = do_shortcode( '[partner_coupon_report]' );
	}
	return $content;
}
add_filter( 'the_content', 'custom_woocommerce_filter_report_content' );