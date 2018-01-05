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
  Drupal.behaviors.voteBehavior = {
    attach: function (context, settings) {
      $('.vote-icon', context).once('voteBehavior').each(function () {
        $(this).click(function(){
          var aid = $(this).attr("id").split('-')[1];
          var cls = $(this).attr("class");
          var url = Drupal.url('journals/add-vote');
          if(cls.includes("voted"))
            url = Drupal.url('journals/remove-vote');
          $.ajax({
            url: url,
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
                if(response.vote_added || response.vote_removed)
                  $('#vote-'+aid).toggleClass('voted');
                if(response.vote_added)
                {
                  $('#vote-'+aid).find("span.tooltiptext").html("Remove vote");
                }
                if(response.vote_removed)
                {
                  $('#vote-'+aid).find("span.tooltiptext").html("Add vote");
                }
                if(cls.includes("voted") && !response.vote_removed)
                  alert("You cannot remove your vote from this article.");
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
  Drupal.behaviors.articleClickBehavior = {
    attach: function (context, settings) {
      $('.article', context).once('articleClickBehavior').each(function () {
        var aid = $(this).attr('id').split('-')[1];
        $(this).click(function() {
          $(this).removeClass('new');
        });
        $('.article-data', this).add('.comment-icon',this).click(function() {
          var comment = $('.article-comment','#article-'+aid );
          var cls = comment.attr('class');
          $('#article-'+aid).toggleClass('expanded');
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

(function ($, Drupal) {
  Drupal.behaviors.articleMenuBehavior = {
    attach: function (context, settings) {
      $('.options-icon', context).once('articleMenuBehavior').each(function () {
        var aid = $(this).parents('.article').attr('id').split('-')[1];
        $('#menu-'+aid).blur(function() {
          $(this).slideUp(100, function(){ $(this).removeClass('visible'); });
        });
        $(this).click(function() {
          if($('#menu-'+aid).attr('class').includes('visible'))
            $('#menu-'+aid).blur();
          else
          {
            $('#menu-'+aid).slideDown(100, function(){
              $(this).addClass('visible');
              $(this).focus();
            });
          }
        });
      });
    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.keepUnreadBehavior = {
    attach: function (context, settings) {
      $('.unread-action', context).once('keepUnreadBehavior').each(function () {
        var aid = $(this).parents('.article').attr('id').split('-')[1];
        $(this).click(function(event) {
          event.stopPropagation();
          $('#article-'+aid).addClass('new');
          $('#menu-'+aid).blur();
          $.ajax({
            url: Drupal.url('journals/mark-unread'),
            type: 'POST',
            data: {
              'article_id' : aid,
              'origin' : window.location.pathname
            },
            dataType: 'json'
          });
        });
      });
    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.recommendOpenBehavior = {
    attach: function (context, settings) {
      $('.share-action', context).once('recommendOpenBehavior').each(function () {
        var aid = $(this).parents('.article').attr('id').split('-')[1];
        $(this).click(function(event) {
          event.stopPropagation();
          $('#menu-'+aid).blur();
          //lookup eligible users and enable autocompletion
          $.ajax({
            url: Drupal.url('journals/get-eligible-recommend'),
            type: 'POST',
            data: {
              'article_id' : aid,
              'origin' : window.location.pathname
            },
            dataType: 'json',
            success: function rec_list(response) {
              var users = response.suggestions;
              $('#recommend-input').val('');
              $('#recommend-article-title').html($(".article-title", "#article-"+aid).html());
              $('#recommend-article-metadata').html($(".article-metadata", "#article-"+aid).html());
              $('#recommend-bg').toggleClass('visible')
              $('#recommend-input').autocomplete({
                lookup: users,
                minchars: 1,
                appendTo: $('#recommend-autocomplete'),
                onSelect: function (suggestion) {
                  $.ajax({
                    url: Drupal.url('journals/recommend'),
                    type:'POST',
                    data: {
                      'article_id' : aid,
                      'user_id' : suggestion.data,
                      'origin' : window.location.pathname
                    },
                    dataType: 'json',
                    success: function rec_action(response2) {
                      if(response2.success)
                      {
                        $('#recommend-success-user').html(suggestion.value);
                        $('#recommend-success').addClass('visible');
                      }
                      else
                      {
                        $('#recommend-fail-user').html(suggestion.value);
                        $('#recommend-fail-msg').html(response2.msg);
                        $('#recommend-fail').addClass('visible');
                      }
                      users = users.filter(function(el){ return el.data != suggestion.data; });
                      $('#recommend-input').autocomplete('setOptions',{
                        lookup: users,
                      });
                      $('#recommend-input').val('');
                    },
                    error: function rec_action_fail(a, b, c) {
                      $('#recommend-fail-user').html(suggestion.value);
                      $('#recommend-fail-msg').html('An error occured when trying to communicate with the server. Try again later.');
                      $('#recommend-fail').addClass('visible');
                    }
                  });
                },
              });
            },
          });
        });
      });
    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.recommendCloseBehavior = {
    attach: function (context, settings) {
      $('#recommend-bg', context).once('recommendCloseBehavior').each(function () {
        $(this).click(function(event) {
          if(!$.contains($(this).get(0),event.target))
          {
            event.stopPropagation();
            $(this).toggleClass('visible');
            $('#recommend-input').val('');
            $('#recommend-article-title').html('');
            $('#recommend-article-metadata').html('');
            $('#recommend-success-user').html('');
            $('#recommend-fail-user').html('');
            $('#recommend-success-user').removeClass('visible');
            $('#recommend-fail-user').removeClass('visible');
            $('#recommend-input').autocomplete().dispose();
          }
        });
      });
    }
  };
})(jQuery, Drupal);
