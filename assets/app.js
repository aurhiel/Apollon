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

      // Vinyl & artist modals
      self.$modal_artist  = self.$body.find('#modal-manage-artist');
      self.$modal_vinyl   = self.$body.find('#modal-manage-vinyl');

      // Vinyls list container
      self.$vinyls = self.$body.find('#vinyls-entities');
      self.$vinyls_total_qty = self.$body.find('.-vinyls-total-quantity');

      // Remove loading (not used yet...)
      self.unload();

      // Enable Bootstrap Tooltips
      // self.$body.find('[data-toggle="tooltip"]').tooltip();

      // Trigger scroll event after ready to display elements already on screen
      // self.$window.trigger('scroll');

      // if (self.$modal_artist.length > 0) {
      //   self.$modal_artist.get(0).addEventListener('show.bs.modal', function () {
      //     console.log('Yay ! ~');
      //   });
      // }

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
              $col_qty.find('.btn-qty[data-qty-type="-1"]').toggleClass('disabled', (r.new_quantity < min_limit))

              if (typeof r.total_vinyls != 'undefined')
                self.$vinyls_total_qty.html(r.total_vinyls);
            }
          }
        });
      });

      // Create YouTube player (iframe & co) using JS
      // var tag = document.createElement('script');
      // tag.src = "https://www.youtube.com/player_api";
      // var firstScriptTag = document.getElementsByTagName('script')[0];
      // firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
      //
      var auth_key = 'AIzaSyAa0biHVpJuov67kzhKwZo2CANor-Z8H3w';
      self.$vinyls.on('click', 'td.col-track', function() {
        var $col    = $(this);
        var track   = $col.html().trim();
        var artist  = $col.parents('tr').first().find('.col-artist').html().trim();

        // Get youtube videos
        $.ajax({
          method: 'GET',
          url: 'https://youtube.googleapis.com/youtube/v3/search?maxResults=1&q=' + track + ' - ' + artist + '&key=' + auth_key,
          success: function(r) {
            if (typeof r.items != 'undefined') {
              // Update artist & track title
              self.$player.find('.-title').html(track);
              self.$player.find('.-artist').html(artist);

              // Update <iframe> source
              self.$player.find('iframe').attr('src', 'https://www.youtube.com/embed/' + r.items[0].id.videoId + '?autoplay=1&fs=0&rel=0&showinfo=0');

              // Display player
              self.$player.removeClass('invisible');
            }
          },
          error: function(r) {
            if (typeof r.responseJSON != 'undefined')
              alert(r.responseJSON.error.message);
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

      // Easter egg > when clicking on heart icon in the footer
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
