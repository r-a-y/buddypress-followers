/* eslint-env browser */
/* global jQuery, ajaxurl */
jQuery(($) => {
  const blogDirs = $('.blogs.dir-list')
  const blogFooter = $('#bpf-blogs-ftr')
  const userBlogs = $('body.bp-user.my-blogs #item-body div.blogs')

  if (blogDirs.length) {
    blogDirs.on('click', 'a[data-follow-blog-id]', function () {
      bpFollowBlogAction($(this), 'directory')
      return false
    })
  }

  if (userBlogs.length) {
    userBlogs.on('click', 'a[data-follow-blog-id]', function () {
      bpFollowBlogAction($(this))
      return false
    })
  }

  if (blogFooter.length) {
    blogFooter.on('click', 'a[data-follow-blog-id]', function () {
      bpFollowBlogAction($(this))
      return false
    })
  }
})

function bpFollowBlogAction (link, context = '') {
  const action = link.data('follow-action')

  let fader = link.parent()
  if (!fader.hasClass('blog-button')) {
    link.wrap('<span class="blog-button"></span>')
    fader = link.parent()
  }

  jQuery.post(ajaxurl, {
    action: 'bp_follow_blogs',
    followData: JSON.stringify(link.data())
  },
  (response) => {
    jQuery(fader.fadeOut(200, () => {
      // toggle classes
      if (action === 'unfollow') {
        fader.removeClass('following').addClass('not-following')
      } else if (action === 'follow') {
        fader.removeClass('not-following').addClass('following')
      }

      // add ajax response
      fader.html(response.data.button)

      // increase / decrease counts
      let countWrapper = false
      if (context === 'directory') {
        countWrapper = jQuery('#blogs-following span:last-child')
      }

      if (countWrapper.length) {
        if (action === 'unfollow') {
          countWrapper.text((countWrapper.text() >> 0) - 1)
        } else if (action === 'follow') {
          countWrapper.text((countWrapper.text() >> 0) + 1)
        }
      }

      fader.fadeIn(200)
    }))
  })
}
