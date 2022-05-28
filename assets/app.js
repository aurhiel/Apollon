/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

require('bootstrap');

// any CSS you import will output into a single css file (app.css in this case)
import './css/app.scss';

import { Tooltip, Popover, Collapse } from 'bootstrap';

var ClipboardJS = require('clipboard');

require('jquery-tablesort');

// Not used
// start the Stimulus application
// import './bootstrap';

var clipboard = {
  $body: null,
  timeoutHideTooltips: {},
  tooltips: {},
  // Trigger tooltip clipboard
  triggerTooltip: function($btn_tooltip, type) {
    var self = this;

    // Destroy tooltip if already exist
    if (typeof self.tooltips[$btn_tooltip] != 'undefined')
      self.tooltips[$btn_tooltip].dispose();

    // Success / Error message (empty or generic error)
    var tooltip_title = 'Copié !';
    if (type != 'success')
      tooltip_title = (self.$body.find($btn_tooltip.data('clipboard-target')).html() == '' ? 'Aucun contenu à copier...' : 'Une erreur est survenue, veuillez ré-essayer.')


    // Create related clipboard tooltip if not exist
    this.tooltips[$btn_tooltip] = new Tooltip($btn_tooltip.get(0), {
      title     : tooltip_title,
      placement : 'bottom',
      trigger   : 'manual'
    });

    // Show tooltip message
    this.tooltips[$btn_tooltip].show();

    // Then dispose tooltip after 1000ms
    clearTimeout(this.timeoutHideTooltips[$btn_tooltip]);
    this.timeoutHideTooltips[$btn_tooltip] = setTimeout(function() {
      self.tooltips[$btn_tooltip].hide();
      delete self.tooltips[$btn_tooltip];
    }, 1000);
  },
  launch: function() {
    var self = this;
    var btn_clipboard = new ClipboardJS('.btn-clipboard');

    // Init nodes
    self.$body = $('body');

    // Init clipboard events to update tooltip, success ...
    btn_clipboard.on('success', function(e) {
      var $btn_tooltip = $(e.trigger);
      $btn_tooltip = (typeof $btn_tooltip.data('clip-tooltip-target') != 'undefined') ? dashboard.$body.find($btn_tooltip.data('clip-tooltip-target')) : $btn_tooltip;
      self.triggerTooltip($btn_tooltip, 'success');
    });
    // ... and error
    btn_clipboard.on('error', function(e) {
      if (e.action == 'copy') {
        var $btn_tooltip = $(e.trigger);
        $btn_tooltip = (typeof $btn_tooltip.data('clip-tooltip-target') != 'undefined') ? dashboard.$body.find($btn_tooltip.data('clip-tooltip-target')) : $btn_tooltip;
        self.triggerTooltip($btn_tooltip, 'error');
      }
    });
  }
}

var app = {
  //
  // Variables
  $body       : null,
  $html_body  : null,
  $window     : null,


  //
  // Functions
  randomInt: function(max, min) {
    return Math.max((typeof min == 'undefined' ? 0 : min), Math.floor(Math.random() * max));
  },
  sortObject: function(object) {
    return Object.keys(object).sort().reduce((r, k) => (r[k] = object[k], r), {});
  },
  // Page loading
  loading : function() {
    this.$body.addClass('is-loading');
  },
  unload : function() {
    this.$body.removeClass('is-loading');
  },
  // Rocket launcher ! > code executed immediately (before document ready)
  launch : function() {
    //
    // Variables (private & public)
    // Le viss
    var self = this;
    // Set nodes
    self.$body      = $('.app-core');
    self.$html_body = $('html, body')
    self.$window    = $(window);

    //
    // Set loading
    self.loading();


    //
    // Doc ready
    (function() {
      console.log('🌱 Radis ! ~');

      //
      // Variables
      self.$player = self.$body.find('.app-player');
      // Header
      self.$header = self.$body.find('.app-header');
      // Modals
      self.$modal_booking = self.$body.find('#modal-manage-booking');
      self.$modal_artist  = self.$body.find('#modal-manage-artist');
      self.$modal_vinyl   = self.$body.find('#modal-manage-vinyl');
      self.$modal_advert  = self.$body.find('#modal-manage-advert');
      self.$modal_confirm = self.$body.find('#modal-confirm-delete');
      // Vinyls list container
      self.$vinyls = self.$body.find('.vinyls-entities');
      self.$vinyls_total_qty = self.$body.find('.-vinyls-total-quantity');
      // Adverts list container
      self.$adverts = self.$body.find('#advers-entities');
      // Booking nodes
      self.$booking_form = self.$modal_booking.find('form');
      self.$booking_input_price = self.$booking_form.find('#booking_price');
      self.$booking_input_title = self.$booking_form.find('#booking_title');
      self.$booking_vinyls = self.$booking_form.find('.vinyls-entities');
      self.$booking_vinyl_row = self.$booking_vinyls.find('tbody > tr').clone();
      self.$booking_total_selected = self.$booking_form.find('.-vinyls-total-selected > .-amount');
      // // Reset vinyls table body
      self.$booking_vinyls.find('tbody').empty();


      //
      // Remove loading (not used yet...)
      self.unload();


      //
      // Clipboard JS
      clipboard.launch();


      //
      // Bootstrap
      // BS: Tooltips
      // self.$body.find('[data-bs-toggle="tooltip"]').tooltip();
      // BS: Popovers - Update allow list
      var myDefaultAllowList = Popover.Default.allowList;
      myDefaultAllowList.span = ['style'];
      //     Popovers - init
      var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
      var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
          return new Popover(popoverTriggerEl);
      });


      //
      // Table sortable
      self.$body.find('.table-sortable').tablesort();


      //
      // Globals / Generics
      // Trigger scroll event after ready to display elements already on screen
      // self.$window.trigger('scroll');
      // Modal:Confirm delete, add link to delete and add custom things (title, body, ...)
      if (self.$modal_confirm.length > 0) {
        var $btn_clicked = null;
        // Add links & custom things just before modal is showed
        self.$modal_confirm.get(0).addEventListener('show.bs.modal', function (e) {
          var $modal_confirm = $(this);
          var $btn_confirm = $modal_confirm.find('.btn-ok');

          $btn_clicked = $(e.relatedTarget);

          // Check if the confirm[data-href] is defined
          if (typeof $btn_clicked.data('confirm-href') != 'undefined') {
            // Reset modal body and set body if defined
            $modal_confirm.find('.modal-body').html('');
            if (typeof $btn_clicked.data('confirm-body') != 'undefined')
              $modal_confirm.find('.modal-body').html($('<div>' + $btn_clicked.data('confirm-body') + '</div>'));

            // Set delete link href
            $btn_confirm.attr('href', $btn_clicked.data('confirm-href'));

            // Set link additionnal CSS class
            if (typeof $btn_clicked.data('confirm-link-class') !== 'undefined')
              $btn_confirm.addClass($btn_clicked.data('confirm-link-class'));

            // Always close modal (click on cancel or submit)
            if ((typeof $btn_clicked.data('confirm-always-close') !== 'undefined') && $btn_clicked.data('confirm-always-close') === true)
              $btn_confirm.attr('data-bs-dismiss', 'modal');

            // Update modal confirm delete z-index & backdrop z-index
            //  (not confirm backdrop but it's working ! ...)
            self.$body.find('.modal').last().css('z-index', 1090);
            self.$body.find('.modal-backdrop').css('z-index', 1080);
          } else {
            console.log('[modal.confirm()] Must define a data-href');
          }
        });

        // When confirm modal is hidden
        self.$modal_confirm.get(0).addEventListener('hidden.bs.modal', function (e) {
          var $modal_confirm = $(this);
          var $btn_confirm = $modal_confirm.find('.btn-ok');

          // Clear custom link CSS classes
          if ($btn_clicked != null && typeof $btn_clicked.data('confirm-link-class') !== 'undefined') {
            $btn_confirm.removeClass($btn_clicked.data('confirm-link-class'));
            $btn_clicked = null;
          }
          // & clear forced dismiss
          $btn_confirm.removeAttr('data-bs-dismiss');

          // Clear shitty forcing backdrop z-index (can't use confirm backdrop upon overs modal)
          self.$body.find('.modal-backdrop').removeAttr('style');
        });
      }
      // Auto-select in multi-select TODO multiple selection
      self.$body.find('.form-multi-select').each(function() {
        var $container = $(this);
        if (typeof $container.data('ms-autoselect') != 'undefined')
          $container.find('input[value="' + $container.data('ms-autoselect') + '"]').prop('checked', true);
      });
      // Event:Images library preview
      self.$body.on('change', '.form-image-lib input', function() {
        var $file_input = $(this);
        var files       = $file_input.get(0).files;
        var $parent     = $file_input.parents('.form-image-lib').first();
        var $library    = $parent.find('.-images-library');

        if (files.length > 0) {
          $library.addClass('-has-images').find('.-item, .-text').not('.-in-database').remove();
          for (var i = 0; i < files.length; i++) {
            var reader = new FileReader();
            reader.onload = function(e) {
              $library.append($('<span class="-item ratio ratio-1x1" style="background-image: url(' + e.currentTarget.result + ');"></span>'));
            };
            reader.readAsDataURL(files[i]);
          }
        } else {
          // Reset if no files selected
          $library.removeClass('-has-images').html($library.data('initial-text'));
        }
      });
      // Event:Delete image
      self.$body.on('click', '.btn-delete-img', function(e) {
        var $modal = self.$modal_advert.length > 0 ? self.$modal_advert : self.$modal_vinyl;

        $.ajax({
          method: 'POST',
          url: $(this).attr('href'),
          success: function(r) {
            if (r.query_status == 1) {
              if (typeof r.image_deleted != 'undefined') {
                var $library = $modal.find('.-images-library');
                var $img_item = $library.find('.-item[data-id-image="' + r.image_deleted.id + '"]')

                // Delete image item in form library
                $img_item.remove();

                // Reset library if no more items
                if ($library.find('.-item').length < 1) {
                  $library.removeClass('-has-images').html($library.data('initial-text'));
                }
              }
            } else {
              alert(r.message_status);
            }
          }
        });

        // Ultimate-stop smashing
        e.preventDefault();
        e.stopPropagation();
        return false;
      });
      // Event:Disable every form's buttons after submitting the form
      self.$body.find('.form').on('submit', function() {
        $(this).find('.btn').addClass('disabled');
      });


      //
      // Vinyls
      // Auto open vinyl modal on edit & add event on modal hide
      if (self.$modal_vinyl.hasClass('-is-edit')) {
        self.$header.find('.btn[name="toggle-modal-form-vinyl"]').trigger('click');

        // Redirect on hide (= cancel edit)
        self.$modal_vinyl.get(0).addEventListener('hide.bs.modal', function(e) {
          window.location.href = "/";

          // Ultimate-stop smashing
          e.preventDefault();
          e.stopPropagation();
          return false;
        });
      }
      // Button to update vinyls quantity (total & sold)
      self.$vinyls.on('click', '.btn-qty', function() {
        var $btn      = $(this);
        var $control  = $btn.parents('.form-control-quantity').first();
        var $col_qty  = $btn.parents('.col-quantity').first();
        var is_quantity_sold  = (typeof $col_qty.data('qty-type') != 'undefined' && $col_qty.data('qty-type') == 'sold');
        var is_quantity_cover = (typeof $col_qty.data('qty-type') != 'undefined' && $col_qty.data('qty-type') == 'cover');
        var min_limit         = (is_quantity_sold || is_quantity_cover) ? 1 : 2;
        var max_qty           = (typeof $control.data('qty-max') != 'undefined') ? parseInt($control.data('qty-max')) : null;
        // Add "-sold" or "-cover" to url in order to update vinyl
        //  quantity sold or vinyls with a cover
        var base_url = '/vinyles/' + $col_qty.data('vinyl-id') + '/quantite'
          + (is_quantity_sold ? '-vendue' : (is_quantity_cover ? '-pochette' : ''));

        $.ajax({
          method: 'POST',
          url: base_url + '/' + $btn.data('qty-type'),
          success: function(r) {
            if (r.query_status != 1) {
              alert(r.message_status);
            } else {
              $col_qty.find('.qty-amount').html(r.new_quantity);
              $col_qty.find('.btn-qty[data-qty-type="-1"]').toggleClass('disabled', (r.new_quantity < min_limit));
              if (max_qty != null)
                $col_qty.find('.btn-qty[data-qty-type="+1"]').toggleClass('disabled', (r.new_quantity >= max_qty))

              if (typeof r.total_vinyls != 'undefined')
                self.$vinyls_total_qty.html(r.total_vinyls);
            }
          }
        });
      });
      // Vinyls selection managment
      var $toolbox_selected_vinyls = self.$body.find('.toolbox-selected-vinyls');
      var $tb_buttons = $toolbox_selected_vinyls.find('.btn-selected-vinyls');
      var $tb_amount = $toolbox_selected_vinyls.find('.-amount');
      var $tb_text = $toolbox_selected_vinyls.find('.-text-selected');
      var user_total_selected = 0;
      var user_vinyls_selected = {};
      self.$vinyls.on('change', '.vinyl-checkbox-is-selected', function() {
        var $checkbox = $(this);
        var $vinyl    = $checkbox.parents('.-item-vinyl').first();
        var vinyl_id  = $vinyl.data('vinyl-id');
        var artists_str = $vinyl.find('.-artists-list-raw').data('value');

        // Add or remove vinyl from selected list
        if ($checkbox.prop('checked') === true) {
          if (typeof user_vinyls_selected[artists_str] == 'undefined') {
            user_vinyls_selected[artists_str] = {
              artists : artists_str,
              tracks  : {}
            };
          }

          // Push new track
          user_vinyls_selected[artists_str].tracks[vinyl_id] = {
            rpm: $vinyl.find('.col-rpm').html(),
            face_A: $vinyl.find('.-vinyl-track-A').html(),
            face_B: $vinyl.find('.-vinyl-track-B').html(),
            quantity_with_cover: $vinyl.find('.col-quantity.-with-cover').data('qty-value')
          };
          user_total_selected++;
        } else {
          delete user_vinyls_selected[artists_str].tracks[vinyl_id];
          user_total_selected--;
        }

        // Update some texts in the selected vinyls toolbox
        $tb_amount.html(user_total_selected);
        $tb_text.html(user_total_selected > 1 ? $tb_text.data('js-text-plural') : $tb_text.data('js-text-singular'));

        // Sort vinyls selected
        user_vinyls_selected = self.sortObject(user_vinyls_selected);

        // Update buttons in toolbox
        if (user_total_selected > 0) {
          $tb_buttons.removeClass('btn-secondary').addClass('btn-primary').prop('disabled', false);
        } else {
          $tb_buttons.removeClass('btn-primary').addClass('btn-secondary').prop('disabled', true);
        }
      });
      // Click on toolbox action buttons (Copy or Book)
      $tb_buttons.on('click', function() {
        var $btn = $(this);

        // Copy or Book vinyls selected
        if ($btn.attr('name') == 'copy') {
          var $target = self.$body.find($btn.data('clipboard-target'));
          var text_to_copy = '';

          // Reset target
          $target.html('');

          var i_artist = 0;
          var nb_artists = Object.keys(user_vinyls_selected).length;
          // Loop on each artist to create text to copy
          for (const artist_name in user_vinyls_selected) {
            var last = (i_artist === (nb_artists - 1));
            var vinyls_selected = user_vinyls_selected[artist_name];
            var tracks = vinyls_selected.tracks;
            var nb_tracks = Object.keys(tracks).length;

            if (nb_tracks > 0) {
              // Add artist name
              text_to_copy += vinyls_selected.artists;

              // Add tracks
              for (const vinyl_id in tracks) {
                // Add vinyl track faces in desc
                if (tracks.hasOwnProperty(vinyl_id)) {
                  var vinyl = tracks[vinyl_id];
                  text_to_copy += ((nb_tracks > 1) ? '<br>': ' ') + '- ' +
                    vinyl.face_A + ' / ' + vinyl.face_B + (vinyl.quantity_with_cover < 1 ? ' (sans pochette)': '');
                }
              }

              // Add breakline after each artist
              if (last == false)
                text_to_copy += '<br>';
            }
            i_artist++;
          }

          // Update text to copy in $target
          if (text_to_copy.length > 0)
            $target.html($('<div>'+text_to_copy+'</div>'));
        } else {
          var i_artist = 0;
          var nb_artists = Object.keys(user_vinyls_selected).length;
          var total_selected = 0;
          var booking_title = '';
          var is_rpm_consistent = true;
          var last_rpm = null;

          // Reset vinyls table
          self.$booking_vinyls.find('tbody').empty();

          // Loop on each artist to create text to copy
          for (const artist_name in user_vinyls_selected) {
            var last = (i_artist === (nb_artists - 1));
            var vinyls_selected = user_vinyls_selected[artist_name];
            var tracks = vinyls_selected.tracks;
            var nb_tracks = Object.keys(tracks).length;

            // Add vinyls to table in booking form
            if (nb_tracks > 0) {
              for (const vinyl_id in tracks) {
                if (tracks.hasOwnProperty(vinyl_id)) {
                  var vinyl = tracks[vinyl_id];
                  var $row = self.$booking_vinyl_row.clone();
                  var $tracks = $row.find('.col-track');
                  var $input_qty = $row.find('.advert-vinyl-qty');
                  var input_qty = $input_qty.prop('outerHTML');

                  // Check rpm consistency to add it to title or not
                  if (last_rpm !== null && last_rpm !== vinyl.rpm) {
                    is_rpm_consistent = false;
                  }

                  // Update vinyl data in table row
                  $row.attr('data-vinyl-id', vinyl_id);
                  $row.find('.col-rpm').html(vinyl.rpm);
                  $tracks.filter('[data-track-face="A"]').find('.-text').html(vinyl.face_A);
                  $tracks.filter('[data-track-face="B"]').find('.-text').html(vinyl.face_B);
                  $row.find('.col-artist').html(artist_name);

                  // Remove input quantity pattern & replace it by the right one
                  $input_qty.remove();
                  $row.find('.col-id').append($(input_qty.replaceAll('#ID#', vinyl_id)));

                  // Append new vinyl row to booking form table
                  self.$booking_vinyls.find('tbody').append($row);

                  last_rpm = vinyl.rpm;
                  ++total_selected;
                }
              }
            }
            i_artist++;
          }

          // Add total vinyls selected & update min price
          self.$booking_total_selected.html(total_selected);
          self.$booking_input_price.attr('min', total_selected).val(total_selected);

          // Create booking title ...
          if (booking_title == '') {
            booking_title = ((nb_artists < 2) ? 'Vinyle ' : 'Lot de ' + total_selected + ' vinyles') + ((is_rpm_consistent) ? ' - ' + last_rpm + 'T' : '');
            // & push artist name if only 1 vinyl selected
            if (nb_artists < 2)
              booking_title += (' - ' + artist_name);
          }
          // ... then add it to hidden input
          console.log(is_rpm_consistent, booking_title);
          self.$booking_input_title.val(booking_title);
        }
      });


      //
      // Adverts
      // Advert vinyls quantity update event
      var total_selected = 0;
      var vinyls_selected = {};
      // var artists_selected = {};
      self.$modal_advert.on('click', '.btn-qty', function() {
        var $btn        = $(this);
        var $control    = $btn.parents('.form-control-quantity').first();
        var $multi_select = $control.parents('.form-multi-select').first();
        var $qty_amount = $control.find('.qty-amount');
        var $qty_input  = $control.find('.advert-vinyl-qty');
        var $form       = $control.parents('form').first();
        var $vinyl      = $control.parents('.-item-vinyl').first();
        var vinyl_id    = $vinyl.data('vinyl-id');
        var $tracks     = $vinyl.find('.-vinyl-tracks');
        var artists_str = $vinyl.find('.-vinyl-artists .-list').html();
        var current_qty = parseInt($control.find('.qty-amount').html());
        var new_qty     = current_qty + parseInt($btn.data('qty-type'));
        var max_qty     = parseInt($control.data('qty-max'));

        if (new_qty <= max_qty && new_qty >= 0) {
          // Disable or enable quantity up/down button
          $control.find('.btn-qty[data-qty-type="-1"]').toggleClass('disabled', (new_qty < 1));
          $control.find('.btn-qty[data-qty-type="+1"]').toggleClass('disabled', (new_qty == max_qty));

          // Update <input> quantity for submit
          $qty_input.val(new_qty);

          // Update new quantity in HTML content
          $qty_amount.html(new_qty);

          total_selected += parseInt($btn.data('qty-type'));
          $multi_select.find('.-vinyls-total-selected .-amount').html(total_selected);

          // Create tracks selected by artists if enough quantity
          if (new_qty > 0) {
            if (typeof vinyls_selected[artists_str] == 'undefined') {
              vinyls_selected[artists_str] = {
                artists : artists_str,
                tracks  : {},
              };
            }

            // Push new track
            vinyls_selected[artists_str].tracks[vinyl_id] = {
              face_A: $vinyl.find('.-vinyl-track-A').html(),
              face_B: $vinyl.find('.-vinyl-track-B').html(),
              quantity: new_qty,
            };
          } else {
            delete vinyls_selected[artists_str].tracks[vinyl_id];
            // console.log(Object.keys(vinyls_selected[artists_str].tracks).length);
          }
        }

        // Sort vinyls selected by artists names
        vinyls_selected = self.sortObject(vinyls_selected);

        // Create advert title & description if enough vinyls quantity & not edit
        if (self.$modal_advert.hasClass('-is-edit') == false) {
          var advert_title = '';
          var advert_desc = '';
          if (total_selected > 0) {
            // Loop on tracks selected to add them into title & description
            var i_artist = 0;
            var nb_artists = Object.keys(vinyls_selected).length;
            for (const artist_name in vinyls_selected) {
              var last = (i_artist === (nb_artists - 1));

              if (vinyls_selected.hasOwnProperty(artist_name)) {
                var tracks = vinyls_selected[artist_name].tracks;
                var nb_tracks = Object.keys(tracks).length;

                if (nb_tracks > 0) {
                  // Init advert_title & _desc
                  if (advert_title == '') {
                    // Create advert title
                    advert_title = ((total_selected < 2) ? 'Vinyle ' : 'Lot de ' + total_selected + ' vinyles - ') + $tracks.data('vinyl-rpm') + 'T';
                    //  & push artist name in title if only 1 vinyl selected
                    if (total_selected < 2)
                      advert_title += (' - ' + artist_name);

                    // Create desc
                    if (total_selected > 1) {
                      advert_desc = 'Je vends ce lot de ' + total_selected + ' vinyles, ' + $tracks.data('vinyl-rpm') + ' tours, comprenant les titres suivants :\r\n';
                    } else {
                      advert_desc = 'Je vends ce vinyle de ' + artist_name + ' composé des morceaux ';
                    }
                  }

                  // Add artist in desc (only when have selected more than 1 vinyl)
                  //  and if there is more than 1 artist we add a "- " before each artists
                  if (total_selected > 1)
                    advert_desc += ((nb_artists > 1 && nb_tracks < 2) ? '- ': '') + artist_name + ((nb_tracks > 1) ? ' :': '');

                  for (const vinyl_id in tracks) {
                    // Add vinyl track faces in desc
                    if (tracks.hasOwnProperty(vinyl_id)) {
                      var vinyl = tracks[vinyl_id];
                      // Multi-vinyl selected
                      if (total_selected > 1) {
                        advert_desc += ((nb_tracks > 1) ? '\r\n': ' ') + '- ' + ((vinyl.quantity > 1) ? vinyl.quantity + 'x ' : '') +
                          vinyl.face_A + ' / ' + vinyl.face_B + ' : 1,00€';
                      } else {
                        // Only 1 vinyl selected : add track faces in title & description
                        advert_desc += '« ' + vinyl.face_A + ' » et « ' + vinyl.face_B + ' »';
                        advert_title += ' - ' + tracks[vinyl_id].face_A + ' / ' + tracks[vinyl_id].face_B;
                      }
                    }
                  }

                  // Add breakline after each artist
                  if (last == false)
                    advert_desc += '\r\n';
                }
              }

              i_artist++;
            }
          }

          // Update advert title <input>, description <textarea> & price <input>
          $form.find('#advert_title').val(advert_title);
          $form.find('#advert_description').val(advert_desc);
          $form.find('#advert_price').val(total_selected > 0 ? total_selected : '');
        }
      });
      // Auto open advert modal on edit & add event on modal hide
      if (self.$modal_advert.hasClass('-is-edit')) {
        self.$header.find('.btn[name="toggle-modal-form-advert"]').trigger('click');

        // Redirect on hide (= cancel edit)
        self.$modal_advert.get(0).addEventListener('hide.bs.modal', function(e) {
          window.location.href = "/annonces";

          // Ultimate-stop smashing
          e.preventDefault();
          e.stopPropagation();
          return false;
        });
      }
      // Event:Display adverts gallery
      self.$adverts.on('click', '.advert-display-gallery', function() {
        var $btn = $(this);
        var $container = $btn.parents('.advert-gallery').first();
        var adverts_msnry = Masonry.data(self.$adverts[0]);

        // Hide button & display gallery
        $btn.addClass('d-none');
        $container.find('.advert-list-imgs').removeClass('d-none');

        // Refresh Masonry items position
        adverts_msnry.layout();
      });
      // Event:Advert is sold / not sold
      self.$ads_total_vinyls_sold = self.$body.find('.-total-vinyls-sold');
      self.$ads_total_price_got   = self.$body.find('.-total-prices-got');
      self.$ads_vinyls_avg_price  = self.$body.find('.-price-vinyls-average');
      self.$ads_avg_price         = self.$body.find('.-price-ads-average');
      self.$adverts.on('change', '.advert-checkbox-is-sold', function(e) {
        var $checkbox = $(this);
        var $advert   = $checkbox.parents('.-item').first();
        var advert_id = $checkbox.val();

        $.ajax({
          method: 'POST',
          url: '/annonces/' + advert_id + '/est-vendue/' + (($checkbox.is(':checked')) ? '1' : '0'),
          success: function(r) {
            if (r.query_status == 1) {
              var new_nb_sold   = parseFloat(self.$ads_total_vinyls_sold.html()) + ($advert.data('advert-total-qty') * ($checkbox.is(':checked') ? 1 : -1));
              var new_total_got = parseFloat(self.$ads_total_price_got.html()) + ($advert.data('advert-price') * ($checkbox.is(':checked') ? 1 : -1));

              // Update datas
              self.$ads_total_vinyls_sold.html(new_nb_sold);
              self.$ads_total_price_got.html(new_total_got + '€');
              self.$ads_vinyls_avg_price.html((Math.round((new_total_got / new_nb_sold) * 100) / 100) + '€');
              self.$ads_avg_price.html((Math.round(new_total_got / self.$ads_avg_price.data('ads-qty'))) + '€');

              // Update CSS classes
              $advert.toggleClass('-is-sold', $checkbox.is(':checked'));
            } else {
              alert(r.message_status);
            }
          }
        });

        // Ultimate-stop smashing
        e.preventDefault();
        e.stopPropagation();
        return false;
      });


      //
      // YouTube player & fun
      // Create YouTube player (iframe & co) using JS
      var auth_key = 'AIzaSyAa0biHVpJuov67kzhKwZo2CANor-Z8H3w';
      self.$vinyls.on('click', 'td.col-track', function() {
        var $col    = $(this);
        var $row    = $col.parents('tr').first();
        var id_vinyl = $row.data('vinyl-id');
        var track_face  = $col.data('track-face');

        // Get youtube videos
        $.ajax({
          method: 'POST',
          url: '/vinyles/' + id_vinyl + '/' + track_face + '/youtube-id',
          success: function(r) {
            if (r.query_status == 1 && r.youtube_id != null) {
              // Update artist & track title
              self.$player.find('.-title').html(r.vinyl.track);
              self.$player.find('.-artist').html(r.vinyl.artists);

              // Update <iframe> source
              self.$player.find('iframe').attr('src', 'https://www.youtube.com/embed/' + r.youtube_id + '?autoplay=1&fs=0&rel=0&showinfo=0');

              // Display player
              self.$player.removeClass('invisible');
            }
          }
        });
      });
      // Click on player close button
      self.$player.on('click', '.-close', function() {
        // Hide player
        self.$player.addClass('invisible');

        // Reset artist & track title & iframe source
        self.$player.find('.-title').html('');
        self.$player.find('.-artist').html('');
        self.$player.find('iframe').attr('src', '');
      });
      // Easter egg:When clicking on heart icon in the footer
      self.$body.on('click', '.app-footer .icon-heart', function() {
        self.$player.find('.-title').html('lofi hip hop radio - beats to study/relax to 🐾');
        self.$player.find('.-artist').html('Chillhop Music');
        self.$player.find('iframe').attr('src', 'https://www.youtube.com/embed/7NOSDKb0HlU');
        self.$player.removeClass('invisible');
      });
    })();
  }
};

// Launching app
app.launch();
