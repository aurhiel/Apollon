/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

require('bootstrap');

// any CSS you import will output into a single css file (app.css in this case)
import './css/app.scss';

// Not used
// start the Stimulus application
// import './bootstrap';

var app = {
  // Variables
  //
  $body       : null,
  $html_body  : null,
  $window     : null,


  // Functions
  //
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
    //

    // Le viss
    var self = this;

    // Set nodes
    self.$body      = $('.app-core');
    self.$html_body = $('html, body')
    self.$window    = $(window);

    //
    // Init functions
    //

    // Set loading
    self.loading();


    //
    // Doc ready
    //

    (function() {
      console.log('🌱 Radis ! ~');

      self.$player = self.$body.find('.app-player');

      // Modals
      self.$modal_artist  = self.$body.find('#modal-manage-artist');
      self.$modal_vinyl   = self.$body.find('#modal-manage-vinyl');
      self.$modal_advert  = self.$body.find('#modal-manage-advert');
      self.$modal_confirm = self.$body.find('#modal-confirm-delete');

      // Vinyls list container
      self.$vinyls = self.$body.find('#vinyls-entities');
      self.$vinyls_total_qty = self.$body.find('.-vinyls-total-quantity');

      // Adverts list container
      self.$adverts = self.$body.find('#advers-entities');

      // Remove loading (not used yet...)
      self.unload();

      // Enable Bootstrap Tooltips
      // self.$body.find('[data-toggle="tooltip"]').tooltip();

      // Trigger scroll event after ready to display elements already on screen
      // self.$window.trigger('scroll');

      // Images library preview
      self.$body.on('change', '.form-image-lib input', function() {
        var $file_input = $(this);
        var files       = $file_input.get(0).files;
        var $parent     = $file_input.parents('.form-image-lib').first();
        var $library    = $parent.find('.-images-library');

        if (files.length > 0) {
          $library.addClass('-has-images').html('');
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

      // Modal: Confirm delete, add link to delete and add custom things (title, body, ...)
      if (self.$modal_confirm.length > 0) {
        self.$modal_confirm.get(0).addEventListener('show.bs.modal', function (e) {
          var $modal_confirm = $(this);
          var $btn_clicked = $(e.relatedTarget);

          // Check if the confirm[data-href] is defined
          if (typeof $btn_clicked.data('confirm-href') != 'undefined') {
            // Reset modal body and set body if defined
            $modal_confirm.find('.modal-body').html('');
            if (typeof $btn_clicked.data('confirm-body') != 'undefined')
              $modal_confirm.find('.modal-body').html($('<div>' + $btn_clicked.data('confirm-body') + '</div>'));

            // Set delete link href
            $modal_confirm.find('.btn-ok').attr('href', $btn_clicked.data('confirm-href'));
          } else {
            console.log('[modal.confirm()] Must define a data-href');
          }
        });
      }

      // Button to update vinyls quantity (total & sold)
      self.$vinyls.on('click', '.btn-qty', function() {
        var $btn      = $(this);
        var $col_qty  = $btn.parents('.col-quantity').first();
        var is_quantity_sold  = (typeof $col_qty.data('qty-type') != 'undefined' && $col_qty.data('qty-type') == 'sold');
        var min_limit         = is_quantity_sold ? 1 : 2;
        // Add "-sold" to url in order to update vinyl quantity sold
        var base_url = '/vinyles/' + $col_qty.data('vinyl-id') + '/quantite' + (is_quantity_sold ? '-vendue' : '');

        $.ajax({
          method: 'POST',
          url: base_url + '/' + $btn.data('qty-type'),
          success: function(r) {
            if (r.query_status != 1) {
              alert(r.message_status);
            } else {
              $col_qty.find('.qty-amount').html(r.new_quantity);
              $col_qty.find('.btn-qty[data-qty-type="-1"]').toggleClass('disabled', (r.new_quantity < min_limit));

              if (typeof r.total_vinyls != 'undefined')
                self.$vinyls_total_qty.html(r.total_vinyls);
            }
          }
        });
      });

      // Advert vinyls quantity update event
      var total_selected = 0;
      // var vinyls_selected = [];
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

        // Create advert title & description if enough vinyls quantity
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
                  if (total_selected < 2) {
                    advert_title += (' - ' + artist_name);
                  }

                  // Create desc
                  if (total_selected > 1) {
                    advert_desc = 'Je vends ce lot de ' + total_selected + ' vinyles, ' + $tracks.data('vinyl-rpm') + ' tours, comprenant les titres suivants :\r\n';
                  } else {
                    advert_desc = 'Je vends ce vinyle de ' + artist_name + ' composé des morceaux ';
                  }
                }

                // Add artist in desc (only when have selected more than 1 vinyl)
                if (total_selected > 1)
                  advert_desc += artist_name + ((nb_tracks > 1) ? ' :': '');

                for (const vinyl_id in tracks) {
                  if (tracks.hasOwnProperty(vinyl_id)) {
                    var vinyl = tracks[vinyl_id];
                    // Add vinyl track faces in desc
                    if (total_selected > 1) {
                      advert_desc += ((nb_tracks > 1) ? '\r\n': ' ') + '- ' + ((vinyl.quantity > 1) ? vinyl.quantity + 'x ' : '') +
                        vinyl.face_A + ' / ' + vinyl.face_B;
                    } else {
                      advert_desc += '« ' + vinyl.face_A + ' » et « ' + vinyl.face_B + ' »';
                    }
                  }
                }

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
      });

      // Event:Advert is sold / not sold
      self.$ads_total_vinyls_sold = self.$body.find('.-total-vinyls-sold');
      self.$ads_total_price_got   = self.$body.find('.-total-prices-got');
      self.$adverts.on('change', '.advert-checkbox-is-sold', function(e) {
        var $checkbox = $(this);
        var $advert   = $checkbox.parents('.-item').first();
        var advert_id = $checkbox.val();

        $.ajax({
          method: 'POST',
          url: '/annonces/' + advert_id + '/est-vendue/' + (($checkbox.is(':checked')) ? '1' : '0'),
          success: function(r) {
            if (r.query_status == 1) {
              // Update datas
              self.$ads_total_vinyls_sold.html(parseFloat(self.$ads_total_vinyls_sold.html()) + ($advert.data('advert-total-qty') * ($checkbox.is(':checked') ? 1 : -1)));
              self.$ads_total_price_got.html(parseFloat(self.$ads_total_price_got.html()) + ($advert.data('advert-price') * ($checkbox.is(':checked') ? 1 : -1)) + '€');
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

      // Create YouTube player (iframe & co) using JS
      // var tag = document.createElement('script');
      // tag.src = "https://www.youtube.com/player_api";
      // var firstScriptTag = document.getElementsByTagName('script')[0];
      // firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

      var auth_key = 'AIzaSyAa0biHVpJuov67kzhKwZo2CANor-Z8H3w';
      self.$vinyls.on('click', 'td.col-track', function() {
        var $col    = $(this);
        var $row    = $col.parents('tr').first();
        var id_vinyl = $row.data('vinyl-id');
        var track_face  = $col.data('track-face');

        // debug
        // console.log('vinyles/' + id_vinyl + '/' + track_face + '/youtube-id');

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

      // Auto-select in multi-select TODO multiple selection
      self.$body.find('.form-multi-select').each(function() {
        var $container = $(this);
        if (typeof $container.data('ms-autoselect') != 'undefined')
          $container.find('input[value="' + $container.data('ms-autoselect') + '"]').prop('checked', true);
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
