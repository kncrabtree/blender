(function ($, Drupal) {
  Drupal.behaviors.inboxBehavior = {
    attach: function (context, settings) {
      $('.done-icon', context).once('inboxBehavior').each(function () {
        $(this).click(function(event){
          event.stopPropagation();
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
                $('#article-'+aid).removeClass('new');
                if(cls.includes("archive"))
                  $('#donetooltip-'+aid).html("Mark as done");
                else
                  $('#donetooltip-'+aid).html("Move to inbox");
                $('#done-'+aid).toggleClass('archive');
              }
              if(response.new_inbox > 0)
                $('#inbox-new-count').addClass('visible');
              else
                $('#inbox-new-count').removeClass('visible');
              $('#inbox-new-count').html(response.new_inbox);
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
        $(this).click(function(event){
          event.stopPropagation();
          var aid = $(this).attr("id").split('-')[1];
          var cls = $(this).attr("class");
          $('#article-'+aid).removeClass('new');
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
                if(response.new_inbox > 0)
                  $('#inbox-new-count').addClass('visible');
                else
                  $('#inbox-new-count').removeClass('visible');
                $('#inbox-new-count').html(response.new_inbox);
                if(response.new_recommend > 0)
                  $('#recommend-new-count').addClass('visible');
                else
                  $('#recommend-new-count').removeClass('visible');
                $('#recommend-new-count').html(response.new_recommend);
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
        $(this).click(function(event){
          event.stopPropagation();
          var aid = $(this).attr("id").split('-')[1];
          var cls = $(this).attr("class");
          var url = Drupal.url('journals/add-vote');
          $('#article-'+aid).removeClass('new');
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
              if(response.new_inbox > 0)
                $('#inbox-new-count').addClass('visible');
              else
                $('#inbox-new-count').removeClass('visible');
              $('#inbox-new-count').html(response.new_inbox);
              if(response.new_recommend > 0)
                $('#recommend-new-count').addClass('visible');
              else
                $('#recommend-new-count').removeClass('visible');
              $('#recommend-new-count').html(response.new_recommend);
              if(response.count > 0)
              {
                $('#vote_count-'+aid).html(response.count);
                $('#vote_count-'+aid).addClass('visible');
              }
              else
              {
                $('#vote_count-'+aid).html(response.count);
                $('#vote_count-'+aid).removeClass('visible');
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
          if($(this).attr('class').includes('new'))
          {
            $.ajax({
              url: Drupal.url('journals/mark-read-if-owner'),
              type: 'POST',
              data: {
                'article_id' : aid,
                'origin' : window.location.pathname
              },
              dataType: 'json',
              success: function(response) {
  //               if(response.remove_new)
                $('#article-'+aid).removeClass('new');
                if(response.new_inbox > 0)
                  $('#inbox-new-count').addClass('visible');
                else
                  $('#inbox-new-count').removeClass('visible');
                $('#inbox-new-count').html(response.new_inbox);
                if(response.new_recommend > 0)
                  $('#recommend-new-count').addClass('visible');
                else
                  $('#recommend-new-count').removeClass('visible');
                $('#recommend-new-count').html(response.new_recommend);
              }
            });
          }
        });
        $('.article-data', this).add('.comment-icon',this).click(function() {
          var comment = $('.article-comment-box','#article-'+aid );
          var cls = comment.attr('class');
          $('#article-'+aid).toggleClass('expanded');
          if(cls.includes('visible'))
            comment.slideUp(100, function(){ $(this).toggleClass('visible'); });
          else
          {
            //fetch comments and display
            $.ajax({
              url: Drupal.url('journals/fetch-article-comments'),
              type: 'POST',
              data: {
                'article_id' : aid,
                'origin' : window.location.pathname
              },
              dataType: 'json',
              success: function showComments(response) {
                $('#comment_display-'+aid).html(response.html);
                $('#comment_count-'+aid).html(response.count);
                if(response.count < 1)
                  $('#comment_count-'+aid).removeClass('visible');
                else
                  $('#comment_count-'+aid).addClass('visible');
                Drupal.attachBehaviors();
                comment.slideDown(100, function(){ $(this).toggleClass('visible'); });
              }
            });
          }
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
        $(this).click(function(event) {
          event.stopPropagation();
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
            dataType: 'json',
            success: function(response) {
              if(response.new_inbox > 0)
                $('#inbox-new-count').addClass('visible');
              else
                $('#inbox-new-count').removeClass('visible');
              $('#inbox-new-count').html(response.new_inbox);
              if(response.new_recommend > 0)
                $('#recommend-new-count').addClass('visible');
              else
                $('#recommend-new-count').removeClass('visible');
              $('#recommend-new-count').html(response.new_recommend);
            }
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
          $('#article-'+aid).removeClass('new');
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
              if(response.new_inbox > 0)
                $('#inbox-new-count').addClass('visible');
              else
                $('#inbox-new-count').removeClass('visible');
              $('#inbox-new-count').html(response.new_inbox);
              if(response.new_recommend > 0)
                $('#recommend-new-count').addClass('visible');
              else
                $('#recommend-new-count').removeClass('visible');
              $('#recommend-new-count').html(response.new_recommend);
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

(function ($, Drupal) {
  Drupal.behaviors.addCommentBehavior = {
    attach: function (context, settings) {
      // Enable local plugins
      CKEDITOR.plugins.addExternal( 'texzilla', '/journals/drupal/modules/custom/blender/ckeditor-plugins/texzilla/src/' );
      $('.comment-add', context).once('addCommentBehavior').each(function () {
        var aid = $(this).parents('.article').attr('id').split('-')[1];
        $(this).click(function(event) {
          $('#textarea-'+aid).ckeditor( function editorReady() {
            var editor = $('#textarea-'+aid).ckeditor();
            editor.val('');
            $('#comment_wrapper-'+aid).removeClass('hidden');
            $('#add_comment-'+aid).addClass('hidden');
            $('#comment_submit-'+aid).click( function submitComment() {
              var comment = editor.val();
              if(comment.length > 0)
              {
                $.ajax({
                  url: Drupal.url('journals/add-comment'),
                  type: 'POST',
                  data: {
                    'article_id' : aid,
                    'origin' : window.location.pathname,
                    'comment' : comment
                  },
                  dataType: 'json',
                  success: function commentAdded() {
                    $('#comment_wrapper-'+aid).addClass('hidden');
                    $('#add_comment-'+aid).removeClass('hidden');
                    $('#comment_submit-'+aid).off('click');
                    //fetch comments and display
                    $.ajax({
                      url: Drupal.url('journals/fetch-article-comments'),
                      type: 'POST',
                      data: {
                        'article_id' : aid,
                        'origin' : window.location.pathname
                      },
                      dataType: 'json',
                      success: function showComments(response) {
                        $('#comment_display-'+aid).html(response.html);
                        $('#comment_count-'+aid).html(response.count);
                        if(response.count < 1)
                          $('#comment_count-'+aid).removeClass('visible');
                        else
                          $('#comment_count-'+aid).addClass('visible');
                        Drupal.attachBehaviors();
                      }
                    });
                  }
                });
              }
            });
            $('#comment_cancel-'+aid).click( function cancelComment() {
              $('#comment_wrapper-'+aid).addClass('hidden');
              $('#add_comment-'+aid).removeClass('hidden');
              $('#comment_submit-'+aid).off('click');
              editor.val('');
            });
          }, {
            extraPlugins: 'texzilla'
          });
        });
      });
    }
  };
})(jQuery, Drupal);


(function ($, Drupal) {
  Drupal.behaviors.editCommentBehavior = {
    attach: function (context, settings) {
      $('.comment-link.edit', context).once('editCommentBehavior').each(function () {
        var cid = $(this).attr('id').split('-')[1];
        var aid = $(this).parents('.article').attr('id').split('-')[1];
        var actions = $(this).parents('.comment-actions');
        $(this).click(function edit_comment() {
          $.ajax({
            url: Drupal.url('journals/fetch-comment'),
            type: 'POST',
            data: {
              'comment_id' : cid,
              'origin' : window.location.pathname
            },
            dataType: 'json',
            success: function create_edit_box(response) {
              actions.addClass('hidden');
              $('#comment-'+cid).children('.comment-body').addClass('hidden');
              var editwrapper = $("<div></div>").addClass('comment-editor-wrapper hidden');
              var editbox = $("<textarea></textarea>");
              var submit = $("<button></button>").html("Submit");
              var cancel = $("<button></button>").html("Cancel");
              editwrapper.append(editbox);
              editwrapper.append(submit);
              editwrapper.append(cancel);
              $('#comment-'+cid).children('.comment-body').after(editwrapper).promise().done( function () {
                editbox.ckeditor( function editEditorReady() {
                  editbox.val(response.text);
                  editwrapper.removeClass('hidden');
                  cancel.click( function cancelEdit() {
                    editwrapper.remove();
                    $('#comment-'+cid).children('.comment-body').removeClass('hidden');
                    actions.removeClass('hidden');
                  });
                  submit.click( function submitEdit() {
                    var comment = editbox.val();
                    editwrapper.remove();
                    actions.removeClass('hidden');
                    $.ajax({
                      url: Drupal.url('journals/edit-comment'),
                      type: 'POST',
                      data: {
                        'comment_id' : cid,
                        'origin' : window.location.pathname,
                        'comment' : comment
                      },
                      dataType: 'json',
                      success: function editSuccess(response2) {
                        //fetch comments and display
                        $.ajax({
                          url: Drupal.url('journals/fetch-article-comments'),
                          type: 'POST',
                          data: {
                            'article_id' : aid,
                            'origin' : window.location.pathname
                          },
                          dataType: 'json',
                          success: function showComments(response3) {
                            $('#comment_display-'+aid).html(response3.html);
                            $('#comment_count-'+aid).html(response3.count);
                            if(response3.count < 1)
                              $('#comment_count-'+aid).removeClass('visible');
                            else
                              $('#comment_count-'+aid).addClass('visible');
                            Drupal.attachBehaviors();
                          }
                        });
                      }
                    });
                  });
                },
                {
                  extraPlugins: 'texzilla'
                });
              });
            }
          });
        });
      });
    }
  };
})(jQuery, Drupal);


(function ($, Drupal) {
  Drupal.behaviors.deleteCommentBehavior = {
    attach: function (context, settings) {
      $('.comment-link.delete', context).once('deleteCommentBehavior').each(function () {
        var cid = $(this).attr('id').split('-')[1];
        var aid = $(this).parents('.article').attr('id').split('-')[1];
        var bg = $('#delete-bg');
        $(this).click(function delete_comment_confirm() {
          var container = $('<div></div>').attr('id','delete-confirm-container');
          bg.append(container);
          var msg = $('<div></div>').html('Are you sure you want to delete this comment?');
          container.append(msg);
          var btns = $('<div></div>').addClass('button-container');
          var yesbutton = $('<button></button>').html("Yes");
          var nobutton = $('<button></button>').html("No");
          btns.append(yesbutton).append(nobutton);
          container.append(btns);
          bg.addClass("visible");
          nobutton.click( function() {
            bg.removeClass('visible');
            bg.html('');
          });
          yesbutton.click( function deleteComment() {
            $.ajax({
              url: Drupal.url('journals/delete-comment'),
              type: 'POST',
              data: {
                'comment_id' : cid,
                'origin' : window.location.pathname
              },
              dataType: 'json',
              success: function(response) {
                bg.removeClass('visible');
                bg.html('');
                //fetch comments and display
                $.ajax({
                  url: Drupal.url('journals/fetch-article-comments'),
                  type: 'POST',
                  data: {
                    'article_id' : aid,
                    'origin' : window.location.pathname
                  },
                  dataType: 'json',
                  success: function showComments(response2) {
                    $('#comment_display-'+aid).html(response2.html);
                    $('#comment_count-'+aid).html(response2.count);
                    if(response2.count < 1)
                      $('#comment_count-'+aid).removeClass('visible');
                    else
                      $('#comment_count-'+aid).addClass('visible');
                    Drupal.attachBehaviors();
                  }
                });
              }
            });
          });
        });
      });
    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.deleteConfirmCloseBehavior = {
    attach: function (context, settings) {
      $('#delete-bg', context).once('deleteConfirmCloseBehavior').each(function () {
        $(this).click(function (event) {
          if(!$.contains($(this).get(0),event.target))
          {
            $(this).removeClass('visible');
            $(this).html('');
          }
        });
      });
    }
  };
})(jQuery, Drupal);
