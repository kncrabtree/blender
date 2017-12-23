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
                $('#article-'+aid).slideUp(50, function(){ $(this).remove(); });
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
  Drupal.behaviors.bookmarkBehavior = {
    attach: function (context, settings) {
      $('.bookmark-icon', context).once('bookmarkBehavior').each(function () {
        $(this).click(function(){
          var aid = $(this).attr("id").split('-')[1];
          var cls = $(this).attr("class");
          $.ajax({
            url: Drupal.url('journals/toggle-bookmark'),
            type: 'POST',
            data: {
              'article_id' : aid,
              'origin' : window.location.pathname
            },
            dataType: 'json',
            success: function(response) {
              if(response.remove)
                $('#article-'+aid).slideUp(50, function(){ $(this).remove(); });
              else
              {
                if(response.bookmark)
                {
                  $('#bookmark-'+aid).find("span.icon-name").html("bookmark");
                  $('#bookmark-'+aid).find("span.tooltiptext").html("Remove bookmark");
                }
                else
                {
                  $('#bookmark-'+aid).find("span.icon-name").html("bookmark_border");
                  $('#bookmark-'+aid).find("span.tooltiptext").html("Add bookmark");
                }
                $('#bookmark-'+aid).toggleClass('bookmarked');
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
              'last_id' : last_id,
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

(function ($, Drupal) {
  Drupal.behaviors.showCommentsBehavior = {
    attach: function (context, settings) {
      $('.article-data', context).once('showCommentsBehavior').each(function () {
        $(this).click(function() {
          var comment = $(this).parents('.article-display').siblings('.article-comment');
          var cls = comment.attr('class');
          $(this).parents('.article').toggleClass('expanded');
          if(cls.includes('visible'))
            comment.slideUp(100, function(){ $(this).toggleClass('visible'); });
          else
            comment.slideDown(100, function(){ $(this).toggleClass('visible'); });

//           $.ajax({
//             url: Drupal.url('journals/more-articles'),
//             type: 'POST',
//             data: {
//               'last_id' : last_id,
//               'origin' : window.location.pathname
//             },
//             dataType: 'json',
//             success: function(response) {
//               $(response.html).insertBefore("#articles-more");
//               if(!response.more)
//                 $('#articles-more').remove();
//               Drupal.attachBehaviors();
//             },
//             error: function(a, b, c) {
//               alert("Error: " + a + ", " + b + ", " + c);
//             }
//           });
        });
      });
    }
  };
})(jQuery, Drupal);
