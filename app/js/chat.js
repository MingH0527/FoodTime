/**
 * chat.js — FOODTIME Support Chat
 * SHARED ADMIN INBOX edition
 *
 * Works for both admin_chat.php and customer_chat.php.
 * Unread badge polling uses fetch_conversation.php?action=unread_counts
 * which now queries chat_message_reads (per-admin) instead of receiver_id + admin_id.
 */

/* ── Message polling (active conversation) ── */
var _pollConvId    = null;
var _pollLastId    = 0;
var _pollTimer     = null;
var _convListTimer = null;
var _isSending     = false;

window.initChatPolling = function(convId, lastId) {
    _pollConvId = convId;
    _pollLastId = lastId || 0;
    clearTimeout(_pollTimer);
    _doPollMessages();
};

function _doPollMessages() {
    if (!_pollConvId) return;
    $.getJSON('fetch_messages.php', { conv_id: _pollConvId, last_id: _pollLastId })
        .done(function(res) {
            if (!res.success) return;

            // Append new messages
            if (res.messages && res.messages.length > 0) {
                res.messages.forEach(function(msg) {
                    _appendMessage(msg);
                    if (msg.message_id_num > _pollLastId) {
                        _pollLastId = msg.message_id_num;
                    }
                });
                var $box = $('#chat-messages');
                $box.scrollTop($box[0].scrollHeight);
            }

            // Update sidebar unread badges (admin only — res.unread_counts exists)
            if (res.unread_counts) {
                _updateBadges(res.unread_counts);
            }
        })
        .always(function() {
            _pollTimer = setTimeout(_doPollMessages, 4000);
        });
}

/* ── Conversation list polling (admin sidebar) ── */
window.startConvListPolling = function() {
    clearTimeout(_convListTimer);
    _doPollConvList();
};

function _doPollConvList() {
    $.getJSON('fetch_conversation.php', { action: 'list' })
        .done(function(res) {
            if (!res.success || !res.conversations) return;

            // Update sidebar badges and conversation previews
            var counts = {};
            res.conversations.forEach(function(c) {
                counts[c.conversation_id] = c.unread;

                // Update unread badge on the conv-item if present
                var $item = $('[data-conv-id="' + c.conversation_id + '"]');
                if ($item.length) {
                    $item.find('.conv-badge').remove();
                    if (c.unread > 0) {
                        $item.append('<span class="conv-badge">' + c.unread + '</span>');
                    }
                    // Update preview text
                    var prev = c.last_message ? c.last_message.substring(0, 40) + (c.last_message.length > 40 ? '…' : '') : 'No messages yet';
                    $item.find('.conv-prev').html(
                        '<span class="sdot dot-' + c.status + '"></span>' + _escHtml(prev)
                    );
                    $item.find('.conv-time').text(c.time_ago);
                }
            });
            _updateBadges(counts);
        })
        .always(function() {
            _convListTimer = setTimeout(_doPollConvList, 8000);
        });
}

function _updateBadges(counts) {
    // Update sidebar total badge
    var total = 0;
    Object.values(counts).forEach(function(v) { total += v; });
    var $b = $('#nav-unread-badge');
    if ($b.length) {
        $b.text(total);
        $b.toggle(total > 0);
    }
}

/* ── Append a single message to the chat window ── */
function _appendMessage(msg) {
    var $box = $('#chat-messages');
    if (!$box.length || !msg || !msg.message_id) return;

    // 防重复：同一个 message_id 已经存在就不要再 append
    if ($box.find('[data-msg-id="' + _escAttr(msg.message_id) + '"]').length) {
        return;
    }

    var type = msg.message_type;
    var html = '';
    var text = _nl2br(_escHtml(msg.message_text || ''));
    var time = msg.created_at_fmt || '';
    var msgIdAttr = _escAttr(msg.message_id);

    if (type === 'system') {
        html = '<div class="msg-group group-system" data-msg-id="' + msgIdAttr + '">'
             + '<div class="msg-row"><div class="msg-bubble bubble-system">' + text + '</div></div>'
             + '</div>';

    } else if (type === 'auto_reply') {
        var botName = (typeof window._isAdminPage !== 'undefined' && window._isAdminPage)
            ? '🤖 AutoBot'
            : '🤖 FOODTIME Support';

        html = '<div class="msg-group group-them" data-msg-id="' + msgIdAttr + '">'
             + '<div class="msg-sender-name">' + botName + '</div>'
             + '<div class="msg-row">'
             + '<div class="msg-av av-bot"><i class="fas fa-robot"></i></div>'
             + '<div><div class="msg-bubble bubble-auto">' + text + '</div>'
             + '<div class="msg-time-label">' + _escHtml(time) + '</div></div>'
             + '</div></div>';

    } else if (msg.is_mine) {
        var bubbleClass = (msg.sender_type === 'admin') ? 'bubble-admin-me' : 'msg-bubble';

        html = '<div class="msg-group group-me" data-msg-id="' + msgIdAttr + '">'
             + '<div class="msg-row"><div>'
             + '<div class="msg-bubble ' + bubbleClass + '">' + text + '</div>'
             + '<div class="msg-time-label">' + _escHtml(time) + '</div>'
             + '</div></div></div>';

    } else if (msg.sender_type === 'admin') {
        html = '<div class="msg-group group-admin" data-msg-id="' + msgIdAttr + '">'
             + '<div class="msg-sender-name">FOODTIME Support</div>'
             + '<div class="msg-row">'
             + '<div class="msg-av av-admin"><i class="fas fa-headset"></i></div>'
             + '<div><div class="msg-bubble bubble-admin">' + text + '</div>'
             + '<div class="msg-time-label">' + _escHtml(time) + '</div></div>'
             + '</div></div>';

    } else {
        var sname = msg.sender_name || 'Customer';

        html = '<div class="msg-group group-them" data-msg-id="' + msgIdAttr + '">'
             + '<div class="msg-sender-name">' + _escHtml(sname) + '</div>'
             + '<div class="msg-row">'
             + '<div class="msg-av av-customer">' + _escHtml(sname.charAt(0).toUpperCase()) + '</div>'
             + '<div><div class="msg-bubble bubble-customer-admin">' + text + '</div>'
             + '<div class="msg-time-label">' + _escHtml(time) + '</div></div>'
             + '</div></div>';
    }

    $box.append(html);
}

/* ── Send message ── */
$(function() {
    // Send on button click or Enter (Shift+Enter = newline)
    $(document).on('click', '#btn-send-msg', function() { _sendMsg(); });
    $(document).on('keydown', '#msg-input', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            _sendMsg();
        }
    });

    // Character counter
    $(document).on('input', '#msg-input', function() {
        var len = $(this).val().length;
        $('#msg-char-counter').text(len + '/400');
        // Auto-resize
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    /* ── New conversation form (customer_chat.php) ── */
    $(document).on('submit', '#form-new-conv', function(e) {
        e.preventDefault();

        var category = $('#conv-category-hidden').val();
        var subject  = $('#conv-subject-hidden').val();
        var orderid  = $('select[name="related_orderid"]').val() || '';

        if (!category) {
            $('#conv-error-text').text('Please select a reason for your enquiry.');
            $('#conv-form-error').addClass('show');
            return;
        }
        if (category === 'Other' && !subject) {
            subject = 'Other';
        }

        var $btn = $('#conv-submit-btn');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Starting…');
        $('#conv-form-error').removeClass('show');

        $.post('create_conversation.php', {
            category:        category,
            subject:         subject,
            related_orderid: orderid
        })
        .done(function(res) {
            if (res.success) {
                window.location.href = 'customer_chat.php?conv_id=' + encodeURIComponent(res.conv_id);
            } else {
                $('#conv-error-text').text(res.message || 'Failed to start conversation.');
                $('#conv-form-error').addClass('show');
                $btn.prop('disabled', false).html('<i class="fas fa-comments"></i> Start Chat');
            }
        })
        .fail(function() {
            $('#conv-error-text').text('Network error. Please try again.');
            $('#conv-form-error').addClass('show');
            $btn.prop('disabled', false).html('<i class="fas fa-comments"></i> Start Chat');
        });
    });

    /* ── Conversation search ── */
    function _doSearch(val) {
        var q = val.toLowerCase().trim();
        $('.conv-item').each(function() {
            var s = $(this).data('search') || '';
            $(this).toggle(!q || s.indexOf(q) !== -1);
        });
    }

    $(document).on('keydown', '#admin-conv-search', function(e) {
        if (e.key === 'Enter') _doSearch($(this).val());
        if (e.key === 'Escape') { $(this).val(''); _doSearch(''); }
    });
    $(document).on('keydown', '#conv-search', function(e) {
        if (e.key === 'Enter') _doSearch($(this).val());
        if (e.key === 'Escape') { $(this).val(''); _doSearch(''); }
    });

    /* ── Customer sort buttons ── */
    $(document).on('click', '#sort-priority-btn', function() {
        $(this).addClass('sort-btn-active');
        $('#sort-reverse-btn').removeClass('sort-btn-active');
        _sortConvList(false);
    });
    $(document).on('click', '#sort-reverse-btn', function() {
        $(this).addClass('sort-btn-active');
        $('#sort-priority-btn').removeClass('sort-btn-active');
        _sortConvList(true);
    });
});

function _sortConvList(reverse) {
    var order = reverse
        ? ['closed','resolved','pending','open']
        : ['open','pending','resolved','closed'];
    var $list = $('#conv-list');
    var $items = $list.find('.conv-item').detach();
    $items.sort(function(a, b) {
        var sa = $(a).find('.sdot').attr('class') || '';
        var sb = $(b).find('.sdot').attr('class') || '';
        var ma = sa.match(/dot-(\w+)/), mb = sb.match(/dot-(\w+)/);
        var ia = ma ? order.indexOf(ma[1]) : 99;
        var ib = mb ? order.indexOf(mb[1]) : 99;
        return ia - ib;
    });
    $list.append($items);
}

/* ── setConvStatus (admin_chat.php status buttons) ── */
window.setConvStatus = function(convId, status) {
    $.post('fetch_conversation.php', { action: 'set_status', conv_id: convId, status: status })
        .done(function(res) {
            if (res.success) {
                _showAlert('Status updated to ' + status + '.', 'success');
                setTimeout(function() { location.reload(); }, 800);
            } else {
                _showAlert(res.message || 'Failed to update status.', 'error');
            }
        })
        .fail(function() { _showAlert('Network error.', 'error'); });
};

/* ── Internal helpers ── */
function _sendMsg() {
    var convId = _pollConvId;
    var $input = $('#msg-input');
    var text   = $input.val().trim();

    if (!convId || !text) return;
    if (_isSending) return;

    _isSending = true;

    var $btn = $('#btn-send-msg');
    $btn.prop('disabled', true);
    $input.prop('disabled', true);

    $.post('send_message.php', { conv_id: convId, message: text })
        .done(function(res) {
            if (res.success) {
                $input.val('').css('height', 'auto');
                $('#msg-char-counter').text('0/400');

                // 不要手动立即 poll，避免和定时 polling 打架
                // 只把现有 timer 清掉，稍后由统一 polling 拉最新数据
                clearTimeout(_pollTimer);
                _pollTimer = setTimeout(_doPollMessages, 250);
            } else {
                _showAlert(res.message || 'Failed to send message.', 'error');
            }
        })
        .fail(function() {
            _showAlert('Network error. Message not sent.', 'error');
        })
        .always(function() {
            $btn.prop('disabled', false);
            $input.prop('disabled', false);
            _isSending = false;
            $input.focus();
        });
}

function _showAlert(msg, type) {
    var $box = $('#chat-alert-box');
    $box.html('<div class="chat-alert ' + type + '"><i class="fas fa-' + (type === 'success' ? 'check' : 'exclamation-circle') + '"></i> ' + _escHtml(msg) + '</div>');
    $box.show();
    setTimeout(function() { $box.hide(); }, 3000);
}

function _escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function _nl2br(s) {
    return s.replace(/\n/g, '<br>');
}
function _escAttr(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}