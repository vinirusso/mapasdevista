<?php


add_action( 'add_meta_boxes', 'mapasdevista_add_custom_box' );


add_action( 'save_post', 'mapasdevista_save_postdata' );


function mapasdevista_add_custom_box() {

        // Lets check which post types have to have a map

        // If it is displayed in at least one map, there will be a metabox to place the post on the map

        $maps = mapasdevista_get_maps();

        $post_types = array();

        foreach ($maps as $map) {
            if (is_array($map['post_types']) && $map['api'] != 'image')
                foreach ($map['post_types'] as $p_type)
                    array_push($post_types, $p_type);
        }

        $post_types = array_unique($post_types);

        foreach ($post_types as $post_type)
            add_meta_box( 'mapasdevista_metabox', __( 'Place it on the map', 'mapasdevista' ), 'mapasdevista_metabox_map', $post_type );


        // And there wilil also be one meta box for each map that uses an image as a map.
        $post_types = array();
        foreach ($maps as $map) {

            if ($map['api'] == 'image') {
                if (is_array($map['post_types']))
                    foreach ($map['post_types'] as $p_type)
                        array_push($post_types, $p_type);

            }

        }

        $post_types = array_unique($post_types);
            foreach ($post_types as $post_type)
                add_meta_box( 'mapasdevista_metabox_image', __( 'Place it on the map', 'mapasdevista' ), 'mapasdevista_metabox_image', $post_type );

}

/**
 * Renderiza o Google Maps na pagina de posts
 */
function mapasdevista_metabox_map() {
    global $post;
    if( !$location=get_post_meta($post->ID, '_mpv_location', true) ) {
        $location = array('lat'=>'', 'lon'=>'');
    }
    $post_pin = get_post_meta($post->ID, '_mpv_pin', true);

    $args = array(
        'post_type' => 'attachment',
        'meta_key' => '_pin_anchor',
    );
    $pins = get_posts($args);

    // Use nonce for verification
    wp_nonce_field( plugin_basename( __FILE__ ), 'mapasdevista_noncename' );
    ?>
    <fieldset>
        <label for="mpv_lat"><?php _e('Latitude', 'mpv');?>:</label>
        <input type="text" class="medium-field" name="mpv_lat" id="mpv_lat" value="<?php echo $location['lat'];?>"/>

        <label for="mpv_lon"><?php _e('Longitude', 'mpv');?>:</label>
        <input type="text" class="medium-field" name="mpv_lon" id="mpv_lon" value="<?php echo $location['lon'];?>"/>

        <input type="button" id="mpv_load_coords" value="Exibir"/>
    </fieldset>
    <div id="mpv_canvas" class="mpv_canvas"></div>
    <fieldset>
        <label for="mpv_search_address"><?php _e('Search address', 'mpv');?>:</label>
        <input type="text" id="mpv_search_address" class="large-field"/>
    </fieldset>


    <h4><?php _e("Available pins", "mapasdevista");?></h4>
    <div class="iconlist">
    <script type="text/javascript">var pinsanchor = { };</script>
    <?php foreach($pins as $pin): $pinanchor = json_encode(get_post_meta($pin->ID, '_pin_anchor', true)); ?>
        <div class="icon">
            <script type="text/javascript">pinsanchor.pin_<?php echo $pin->ID;?>=<?php echo $pinanchor;?>;</script>
            <div class="icon-image"><label for="pin_<?php echo $pin->ID;?>"><?php echo  wp_get_attachment_image($pin->ID, array(64,64));?></label></div>
            <div class="icon-info">
            <input type="radio" name="mpv_pin" id="pin_<?php echo $pin->ID;?>" value="<?php echo $pin->ID;?>"<?php if($post_pin==$pin->ID) echo ' checked';?>/>
                <span class="icon-name"><?php echo $pin->post_name;?></span>
            </div>
        </div>
    <?php endforeach;?>
    </div>
    <div class="clear"></div>

    <?php
}

/**
 * Save image from metabox
 */
function mapasdevista_save_postdata($post_id) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;

    if ( !wp_verify_nonce( $_POST['mapasdevista_noncename'], plugin_basename( __FILE__ ) ) )
            return;

    global $wp_post_types;
    $cap = $wp_post_types[$_POST['post_type']]->cap->edit_post;

    if ( !current_user_can( $cap, $post_id ) )
        return;

    // save
    if(isset($_POST['mpv_lat']) && isset($_POST['mpv_lon'])) {
        $location = array();
        $location['lat'] = floatval(sprintf("%f", $_POST['mpv_lat']));
        $location['lon'] = floatval(sprintf("%f", $_POST['mpv_lon']));

        if($location['lat'] !== floatval(0) && $location['lon'] !== floatval(0)) {
            update_post_meta($post_id, '_mpv_location', $location);
        } else {
            delete_post_meta($post_id, '_mpv_location');
        }
    }

    if(isset($_POST['mpv_pin']) && is_numeric($_POST['mpv_pin'])) {
        $pin_id = intval(sprintf("%d", $_POST['mpv_pin']));
        if($pin_id > 0) {
            update_post_meta($post_id, '_mpv_pin', $pin_id);
        }
    }

    if(isset($_POST['mpv_img_pin']) && is_array($_POST['mpv_img_pin'])) {
        foreach($_POST['mpv_img_pin'] as $page_id => $pin_id) {
            if(is_numeric($page_id) && is_numeric($pin_id)) {
                $page_id = intval($page_id);
                $pin_id = intval($pin_id);
                update_post_meta($post_id, "_mpv_img_pin_{$page_id}", $pin_id);
            }
        }
    }

    if(isset($_POST['mpv_img_coord']) && is_array($_POST['mpv_img_coord'])) {
        foreach($_POST['mpv_img_coord'] as $page_id => $coord) {
            if(is_numeric($page_id) && preg_match('/^(-?[0-9]+),(-?[0-9]+)$/', $coord, $coord)) {
                $page_id = intval($page_id);
                $coord = "{$coord[1]},{$coord[2]}";
                update_post_meta($post_id, "_mpv_img_coord_{$page_id}", $coord);
                add_post_meta($post_id, "_mpv_in_img_map", $page_id);
            }
        }
    }
}


function mapasdevista_metabox_image() {
    global $post_type, $post;
    $mapsToDisplay = array();

    $args = array(
        'post_type' => 'attachment',
        'meta_key' => '_pin_anchor',
    );
    $pins = get_posts($args);
?>
    <div class="iconlist" id="image-maps">
        <?php
        $maps = mapasdevista_get_maps();
        foreach ($maps as $map):
            if (is_array($map['post_types']) && $map['api'] == 'image' && in_array($post_type, $map['post_types'])): ?>

            <div class="icon">
                <div class="icon-image"><?php echo get_the_post_thumbnail($map['page_id'], array(64,64), array('id'=>"im-{$map['page_id']}"));?></div>
                <div class="icon-info">
                    <input type="checkbox" name="mpv_img_coord[<?php echo $map['page_id']?>]"
                                           id="mpv_img_coord_<?php echo $map['page_id']?>"
                                           value="<?php echo get_post_meta($post->ID, "_mpv_img_coord_{$map['page_id']}", true); ?>" />

                    <label for="mpv_img_coord_<?php echo $map['page_id']?>"><?php echo $map['name']?></label>

                    <input type="hidden" name="mpv_img_pin[<?php echo $map['page_id']?>]"
                                         id="mpv_img_pin_<?php echo $map['page_id']?>"
                                         value="<?php echo get_post_meta($post->ID, "_mpv_img_pin_{$map['page_id']}", true); ?>" />
                </div>
            </div>

        <?php endif; endforeach;?>
    </div>
    <div class="clear"></div>

    <div id="dialog">
        <div class="iconlist">
            <?php foreach($pins as $pin): ?>
                <div class="icon" id="icon-<?php echo $pin->ID;?>"><?php echo wp_get_attachment_image($pin->ID, array(64,64));?></div>
            <?php endforeach;?>
        </div>
        <div class="panel"><img class="pin"/></div>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function() {
            var $ = jQuery;

            // the size of visible area in browser
            function available_height() { return Math.floor($(window).height()*0.95); }
            function available_width() { return Math.floor($(window).width()*0.95); }

            var $dialog = $('#dialog').dialog({
                            'modal': true,
                            'autoOpen' : false,
                            'title': "Pin location"
                        });
            var $panel = $('#dialog .panel')
                       .mousedown(function(ed) {
                           if(ed.target.className.match(/pin/)) {
                               return false;
                           }

                           var start_Y = this.scrollTop;
                           var start_x = this.scrollLeft;

                           $panel.mousemove(function(em) {
                               this.scrollTop  = start_Y + ed.pageY - em.pageY;
                               this.scrollLeft = start_x + ed.pageX - em.pageX;
                           });
                       });
            $(document).mouseup(function(e){ $panel.unbind('mousemove'); });

            // vary according to user selection
            var $map_pin_input = null;
            var $map_coords_input = null;

            // make a draggable pin and create event that
            // stores pin coords when dragging stops
            var $pin = $panel.find('img.pin').draggable({
                'stop': function(e,ui) {
                    var coord = ($pin.css('left')+","+$pin.css('top')).replace(/px/g,'');
                    $map_coords_input.val(coord);
                }
            });

            // event that let user change $pin image by choosing
            // available images in the .iconlist inside #dialog box
            $dialog.find('.iconlist .icon').click(function() {
                var $img = $(this).find('img');
                $map_pin_input.val($(this).attr('id').replace(/^[^0-9]+/,''));
                $pin.attr('src', $img.attr('src'));

                $dialog.find('.iconlist .icon').removeClass('selected');
                $(this).addClass('selected');
            });

            $("#image-maps img").click(function(e) {
                if($panel.find('img').length > 1){
                    $panel.find('img:last').remove();
                }

                // load a new Image object to get real dimensions
                var image = new Image();
                image.src = this.src;
                $panel.append(image);

                // remove the really annoying browser behavior
                image.onmouseup   = function(e) {return false;};
                image.onmousedown = function(e) {return false;};
                image.onmousemove = function(e) {return false;};

                // chrome workaround
                var dim = {w: Math.min(image.width, available_width()),
                           h: Math.min(image.height, available_height())};

                // the dialog dimensions
                $dialog.dialog('option', 'width', dim.w)
                       .dialog('option', 'height', dim.h);

                // bind the coord input to be used in this map
                $map_coords_input = $(this).parents('.icon')
                                        .find('input[type=checkbox]')
                                        .attr('checked',true);

                // bind the pin input to be used in this map
                $map_pin_input = $(this).parents('.icon')
                                        .find('input[type=hidden]');

                // load the selected if exist, otherwise select first from .iconlist
                var icon_id = $map_pin_input.val();
                if(icon_id) {
                    $dialog.find('.iconlist .icon').removeClass('selected');
                    $pin.attr('src', $dialog.find('.iconlist #icon-'+icon_id+' img').attr('src'));
                    $dialog.find('.iconlist #icon-'+icon_id).addClass('selected');
                } else {
                    $dialog.find('.iconlist .icon').removeClass('selected');
                    $pin.attr('src', $dialog.find('.iconlist .icon:first img').attr('src'));
                    $dialog.find('.iconlist .icon:first').addClass('selected');
                }

                // set pin_coords to string avoid 'null' error
                var pin_coords = ($map_coords_input.val()||'').match(/^(-?[0-9]+),(-?[0-9]+)$/);

                // move pin to stored position if exist, otherwise 0,0
                if(pin_coords) {
                    $pin.css('left', pin_coords[1]+"px")
                        .css('top', pin_coords[2]+"px");
                } else {
                    $pin.css('top', 0).css('left', 0);
                }

                // finally, open the dialog
                $dialog.dialog('open');
            });
        });
    </script>

<?php
}
