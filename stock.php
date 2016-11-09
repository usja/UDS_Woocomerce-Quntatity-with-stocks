<?php
/*
Plugin Name: Stock
Plugin URI: http://uds.kiev.ua
Description: Stock control
Version: 1.0.0
Author: Uskov
Author URI: http://uskov.com.ua
*/


global $db_shops;
global $db_stock;
global $db_postmeta;
global $wpdb;
$db_shops = $wpdb->prefix . 'uds_stock_shops';
$db_stock = $wpdb->prefix . 'uds_stock_count';
$db_postmeta = $wpdb->prefix . 'postmeta';

// Install
function uds_stock_install() {
	global $wpdb;
	global $db_shops;
	global $db_stock;

	if($wpdb->get_var("show tables like '$db_shops'") != $db_shops)
	{


		$sql2 =
			"CREATE TABLE {$db_stock} (
         id int(11) unsigned NOT NULL auto_increment ,
         pid int(11) unsigned NOT NULL ,
         id_shop smallint(4) NOT NULL,
         count smallint(10) NOT NULL,
         PRIMARY KEY  (id),
         KEY id_shop (id_shop)
         )
          COLLATE {$wpdb_collate}";

		$sql =
			"CREATE TABLE {$db_shops} (
         id_stock smallint(4) unsigned NOT NULL auto_increment ,
         stockname varchar(255) NULL,
         PRIMARY KEY  (id_stock),
         KEY stockname (stockname)
         )
         COLLATE {$wpdb_collate}";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		dbDelta($sql2);

	}

}
register_activation_hook(__FILE__,'uds_stock_install');

// End install


// Menu
// Hook for adding admin menus
add_action('admin_menu', 'uds_stock_add_pages');

// action function for above hook

/**
 * Adds a new top-level page to the administration menu.
 */
function uds_stock_add_pages() {
	add_menu_page(
		__( 'Products', 'uds_stock' ),
		__( 'Products','uds_stock' ),
		'manage_options',
		'uds_stock_getgoods',
		'uds_stock_getgoods_callback',
		''
	);
}

/** Back-end for update table
 *
 */
add_action('wp_ajax_uds_stock_update_php_table', 'uds_stock_update_php_table_callback');
function uds_stock_update_php_table_callback() {

	parse_str($_POST['data'], $searcharray);
	$data = $searcharray['data'];

	if (count($data)){
		global $wpdb;
		global $db_stock;

		foreach ($data as $pid=>$stock){

			if (count($stock)){
				$total  =0;
				foreach ($stock as $shop=>$qty)
				{
					$total+=$qty;
					$wpdb->delete( $db_stock, array(
							'pid' => $pid,
							'id_shop' => $shop,

						),
						array(
							'%d',
							'%d'
						)  );
					if ($qty > 0){
						$wpdb->insert(
							$db_stock,
							array(
								'pid' => $pid,
								'id_shop' => $shop,
								'count' => $qty
							),
							array(
								'%d',
								'%d',
								'%d'
							)
						);
					}
				}
				/// update stock qty of product
				global $db_postmeta;
				$wpdb->delete( $db_postmeta, array(
							'post_id' => $pid,
							'meta_key' => '_stock'

						)  );

				if ($total > 0){
						$wpdb->insert(
							$db_postmeta,
							array(
								'post_id' => $pid,
								'meta_key' => '_stock',
								'meta_value' => $total
							)
						);

					}

				
			}

		}
	}
	echo $total;
	wp_die();
}

/**
 * Display products/stocks
 */
function uds_stock_getgoods_callback() {
	?>	<script type="text/javascript" >
	function send_data_stock(formid,pid){
		var data = {
			action: 'uds_stock_update_php_table',
			data:  formid.serialize()
		};
	jQuery.post( ajaxurl, data ,function(response) {
			jQuery('#uds_stock_total_'+pid).html(response)
		});
	}
	</script>
   <div class="wrap">
     <h1><?php echo __('Products','uds_stock');?></h1>
      <table class="widefat fixed" cellspacing="0">

	     <tr class="alternate">
		     <td>&nbsp;</td>
<?php /////// Show stocks ////// ?>
<?php
	global $wpdb;
	global $db_shops;
	global $db_stock;
	$sql = "SELECT * FROM ".$db_shops;
	$result = $wpdb->get_results($sql);
	if ($result){
		$total_stock = 0;
		$arr_stocks = array();
		foreach ($result as $item){
			$arr_stocks[$total_stock] = $item->id_stock;
			$total_stock++;
?>
				<td  class="column-columnname"><?php echo $item->stockname;?></td>
				<?php
		}

	}
?>
        <td class="column-columnname"><?php echo __('Total','uds_stock');?></td>
     </tr>
     <?php

	$paged = ( $_GET['paged'] ) ? $_GET['paged'] : 1;

	$full_product_list = array();
	$loop = new WP_Query( array( 'post_type' => array('product', 'product_variation'), 'posts_per_page' =>  get_option('uds_stock_perpage'), 'paged' => $paged ) );

	while ( $loop->have_posts() ) : $loop->the_post();
	$theid = get_the_ID();
	$product = new WC_Product($theid);

	if( get_post_type() == 'product_variation' ){
		$parent_id = wp_get_post_parent_id($theid );
		$sku = get_post_meta($theid, '_sku', true );
		$thetitle = get_the_title( $parent_id);
		if ($sku == '') {
			if ($parent_id == 0) {
				$false_post = array();
				$false_post['ID'] = $theid;
				$false_post['post_status'] = 'auto-draft';
				wp_update_post( $false_post );
			} else {
				$sku = get_post_meta($parent_id, '_sku', true );
				update_post_meta($theid, '_sku', $sku );
				update_post_meta($parent_id, '_sku', '' );
			}
		}
	} else {
		$sku = get_post_meta($theid, '_sku', true );
		$thetitle = get_the_title();
	}
	if (!empty($sku)) $full_product_list[] = array($thetitle, $sku, $theid);
	endwhile;
	wp_reset_query();
	//sort by name//

	if (count($full_product_list)){
		sort($full_product_list);
		foreach ($full_product_list as $item){
			$sql = "SELECT * FROM ".$db_stock. " WHERE `pid` =".$item[2];
			$resultcount = $wpdb->get_results($sql);
			if ($resultcount > 0){
				foreach ($resultcount as $item_count){
					$ar_count_data[$item_count->pid][$item_count->id_shop]= $item_count->count;
				}
			}


			?><form id="uds_stock_<?php echo $item[2];?>"><tr><td><b><?php echo $item[0];?></b><br/><?php echo $item[1];?> <span>(ID:<?php echo $item[2];?>)</span></td>
			<?php
			// make inputs
			if (count($arr_stocks)){
				$total = 0;
				foreach ($arr_stocks as $ar_obj=>$val){
					?><td>
						<input type="text" name="data[<?php echo $item[2];?>][<?php echo $val;?>]" id="data[<?php echo $item[2];?>][<?php echo $val;?>]" value="<?php echo $ar_count_data[$item[2]][$val]?>"/>
						<?php $total+=$ar_count_data[$item[2]][$val]?>
						</td><?php
				}
			}

?>
				<td>
					<span id="uds_stock_total_<?php echo $item[2];?>"><?php echo $total;?></span>
					<input type="button" value="<?php echo __('Save','uds_stock');?>" onclick="send_data_stock(jQuery('#uds_stock_<?php echo $item[2];?>'),<?php echo $item[2];?>);"/></td>
			</tr></form><?php
		}
	}




	$max_pages =   $loop->max_num_pages;
	$nextpage = $paged + 1;

?>


      </table>


<?php
/*	if ($max_pages > $paged) {
		echo '<a href="admin.php?page=uds_stock_getgoods&paged='. $nextpage .'">Load More Topics</a>';
	}
	$prevpage = max( ($paged - 1), 0 ); //max() will discard any negative value
	if ($prevpage !== 0) {
		echo '<a href="admin.php?page=uds_stock_getgoods&paged='. $prevpage .'">Previous page</a>';
	}
*/		
		$page_links = paginate_links( array(
    'base' => '%_%',
    'format' => 'admin.php?page=uds_stock_getgoods&paged=%#%',
    'prev_text' => __( '&laquo;', 'text-domain' ),
    'next_text' => __( '&raquo;', 'text-domain' ),
    'total' => $max_pages,
    'current' => $paged
) );

if ( $page_links ) {
    echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
}

	?></div><?php

}


add_action('admin_menu', 'wpdocs_register_my_custom_submenu_page');

function wpdocs_register_my_custom_submenu_page() {
	add_submenu_page(
		'uds_stock_getgoods',
		__( 'Stocks','uds_stock' ),
		__( 'Stocks','uds_stock' ),
		'manage_options',
		'uds_stock_stocklist',
		'uds_stock_stocklist_callback' );
		
		add_submenu_page(
		'uds_stock_settings',
		__( 'Settings','uds_stock' ),
		__( 'Settings','uds_stock' ),
		'manage_options',
		'uds_stock_settings',
		'uds_stock_settings_callback' );
}

function uds_stock_settings_callback() {
	?><div class="wrap">
<h2><?=__('Stock config','uds_stock');?></h2>

<form method="post" action="options.php">
<?php wp_nonce_field('update-options'); ?>

<table class="form-table">

<tr valign="top">
<th scope="row"><?=__('Show products per page','uds_stock');?></th>
<td><input type="text" name="uds_stock_perpage" value="<?php echo get_option('uds_stock_perpage'); ?>" /></td>
</tr>
 
<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="uds_stock_perpage" />

<p class="submit">
<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>

</form>
</div><?
	}
function uds_stock_stocklist_callback() {
	global $wpdb;
	global $db_shops;





	// Add stock //
	if (isset($_POST['uds_addstock'])){

		$wpdb->insert(
			$db_shops,
			array(
				'stockname' => htmlspecialchars($_POST['uds_addstock']),

			),
			array('%s')
		);
	}
	// end add stock


	// show stock list
	echo '<div class="wrap">';
	echo '<h2>'.__( 'Stocks','uds_stock' ).'</h2>';
?>
        <form method="POST">
	        <?php echo __('Add stock','uds_stock');?><input type="text" name="uds_addstock" />
	        <input type="submit"/>
        </form>
        <?php
	global $wpdb;
	global $db_shops;
	$sql = "SELECT * FROM ".$db_shops;
	$result = $wpdb->get_results($sql);
	if ($result){
		?><table class="widefat fixed" cellspacing="0"><tr class="alternate"><td  class="column-columnname"><?php echo __('Name','uds_stock');?></td><td  class="column-columnname"><?php echo __('Actions', 'uds_stock');?></td></tr><?php
		foreach ($result as $item){
?>
				<tr ><td  class="column-columnname"><?php echo $item->stockname;?></td><td> <div class="row-actions"><span><a href="#"><?php echo __('Delete','uds_stock');?></a></span></div></td></tr>
				<?php
		}
		?></table><?php
	}

	echo '</div>';
}
//end menu



/// no need @todo fuck-off
add_filter( 'woocommerce_get_availability', 'custom_get_availability', 1, 2);

function custom_get_availability( $availability, $_product ) {
  global $product;
  $stock = $product->get_total_stock();

  if ( $_product->is_in_stock() ) $availability['availability'] = __($stock . ' SPOTS LEFT', 'woocommerce');
  if ( !$_product->is_in_stock() ) $availability['availability'] = __('SOLD OUT', 'woocommerce');

  return $availability;
}


//add settings link

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'uds_stock_action_links' );

function uds_stock_action_links( $links ) {
   $links[] = '<a href="'. esc_url( get_admin_url(null, 'admin.php?page=uds_stock_settings') ) .'">'.__('Settings','uds_stock').'</a>';
    return $links;
}





?>
