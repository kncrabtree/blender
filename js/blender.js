(function ($, Drupal) {
  Drupal.behaviors.inboxBehavior = {
    attach: function (context, settings) {
      $('.done-icon', context).once('inboxBehavior').each(function () {
        $(this).click(function(){
          var aid = $(this).attr("id").split('-')[1];
          var cls = $(this).attr("class");
          $.ajax({
            url: Drupal.url('journals/toggle-archive'),
            type: 'POST',
            data: {
              'article_id' : aid,
              'origin' : window.location.pathname
            },
            dataType: 'json',
            success: function(response) {
              if(response.remove)
                $('#article-'+aid).remove();
              else
              {
                if(cls.includes("archive"))
                  $('#donetooltip-'+aid).html("Mark as done");
                else
                  $('#donetooltip-'+aid).html("Move to inbox");
                $('#done-'+aid).toggleClass('archive');
              }
            },
            error: function(a, b, c) {
              alert("Error: " + a + ", " + b + ", " + c);
            }
          });
        });
      });
    }
  };
})(jQuery, Drupal);


(function ($, Drupal) {
  Drupal.behaviors.moreArticlesBehavior = {
    attach: function (context, settings) {
      $('#articles-more', context).once('moreArticlesBehavior').each(function () {
        $(this).click(function() {
          var last_id = $('.article:last').attr("id").split('-')[1];
          $.ajax({
            url: Drupal.url('journals/more-articles'),
            type: 'POST',
            data: {
              'last_article_id' : last_id,
              'origin' : window.location.pathname
            },
            dataType: 'json',
            success: function(response) {
              $(response.html).insertBefore("#articles-more");
              if(!response.more)
                $('#articles-more').remove();
              Drupal.attachBehaviors();
            },
            error: function(a, b, c) {
              alert("Error: " + a + ", " + b + ", " + c);
            }
          });
        });
      });
    }
  };
})(jQuery, Drupal);
