function likeUnlikePost(post_id) {
    if (jQuery(".like-post-id-" + post_id).hasClass('liked')) {
        jQuery(".like-post-id-" + post_id).removeClass('liked');
        likeDislikePostAPI({post_id: post_id, type: 'unlike'});
        var like_count = jQuery(".like-count-post-id-" + post_id + ":eq(0)").text();
        jQuery(".like-count-post-id-" + post_id).text(parseInt(like_count) - 1);
    } else {
        if (jQuery(".dislike-post-id-" + post_id).hasClass('disliked')) {
            jQuery(".dislike-post-id-" + post_id).removeClass('disliked');
            likeDislikePostAPI({post_id: post_id, type: 'undislike'});
            var dislike_count = jQuery(".dislike-count-post-id-" + post_id + ":eq(0)").text();
            jQuery(".dislike-count-post-id-" + post_id).text(parseInt(dislike_count) - 1);
        }
        jQuery(".like-post-id-" + post_id).addClass('liked');
        likeDislikePostAPI({post_id: post_id, type: 'like'});
        var like_count = jQuery(".like-count-post-id-" + post_id + ":eq(0)").text();
        console.log(like_count);
        jQuery(".like-count-post-id-" + post_id).text(parseInt(like_count) + 1);
    }
    return false;
}

function dislikeUndislikePost(post_id) {
    if (jQuery(".dislike-post-id-" + post_id).hasClass('disliked')) {
        jQuery(".dislike-post-id-" + post_id).removeClass('disliked');
        likeDislikePostAPI({post_id: post_id, type: 'undislike'});
        var dislike_count = jQuery(".dislike-count-post-id-" + post_id + ":eq(0)").text();
        jQuery(".dislike-count-post-id-" + post_id).text(parseInt(dislike_count) - 1);
    } else {
        if (jQuery(".like-post-id-" + post_id).hasClass('liked')) {
            jQuery(".like-post-id-" + post_id).removeClass('liked');
            likeDislikePostAPI({post_id: post_id, type: 'unlike'});
            var like_count = jQuery(".like-count-post-id-" + post_id + ":eq(0)").text();
            jQuery(".like-count-post-id-" + post_id).text(parseInt(like_count) - 1);
        }
        jQuery(".dislike-post-id-" + post_id).addClass('disliked');
        likeDislikePostAPI({post_id: post_id, type: 'dislike'});
        var dislike_count = jQuery(".dislike-count-post-id-" + post_id + ":eq(0)").text();
        jQuery(".dislike-count-post-id-" + post_id).text(parseInt(dislike_count) + 1);
    }
    return false;
}

function likeDislikePostAPI(data) {
    data.security = likeDislikeVars.security;
    data.action = likeDislikeVars.action;
    jQuery.ajax({
        url: likeDislikeVars.url,
        type: 'post',
        data: data,
        success: function (response) {
            console.log(response);
        }
    });
}