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

        $.ajax({
          method: 'POST',
          url: '/vinyles/' + $col_qty.data('vinyl-id') + '/quantite/' + $btn.data('qty-type'),
          success: function(r) {
            if (r.query_status != 1) {
              alert(r.message_status);
            } else {
              $col_qty.find('.qty-amount').html(r.new_quantity);
              $col_qty.find('.btn-qty[data-qty-type="-1"]').toggleClass('disabled', (r.new_quantity < 2))
              self.$vinyls_total_qty.html(r.total_vinyls);
            }
          }
        });
      });
    })();
  }
};

// Launching app
app.launch();
