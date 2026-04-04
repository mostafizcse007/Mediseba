let chatAppointmentId = null;
let chatLastMessageId = 0;
let chatCurrentUserRole = null;
let chatPollingTimer = null;
let chatInitialLoadComplete = false;
let chatRequestInFlight = false;
let chatCanSendMessages = false;

function normalizeChatRole(role) {
    return String(role || '').trim().toLowerCase();
}

function getChatAppointmentId() {
    const params = new URLSearchParams(window.location.search);
    return params.get('appointment') || params.get('id');
}

function getChatBackLink() {
    if (!chatAppointmentId) {
        return Auth.hasRole('doctor') ? 'doctor-appointments.html' : 'appointments.html';
    }

    return `appointment.html?id=${encodeURIComponent(chatAppointmentId)}&v=20260403-2`;
}

function renderChatAvatar(message) {
    const photoPath = resolveAssetPath(message.sender_profile_photo || '');

    if (photoPath) {
        return `
            <div class="chat-avatar">
                <img
                    src="${escapeHtml(photoPath)}"
                    ${resolveLegacyAssetPath(message.sender_profile_photo) ? `data-legacy-src="${escapeHtml(resolveLegacyAssetPath(message.sender_profile_photo))}"` : ''}
                    alt="${escapeHtml(message.sender_name || 'User')}"
                    onerror="handleAssetImageError(this)"
                >
            </div>
        `;
    }

    const initial = escapeHtml(buildAvatarInitial(message.sender_name, '?'));
    return `<div class="chat-avatar">${initial}</div>`;
}

function formatChatTimestamp(dateString) {
    if (!dateString) {
        return '';
    }

    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return escapeHtml(String(dateString));
    }

    const now = new Date();
    const isSameDay =
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate();

    const timePart = date.toLocaleString('en-BD', {
        hour: 'numeric',
        minute: '2-digit'
    });

    if (isSameDay) {
        return timePart;
    }

    const datePart = date.toLocaleString('en-BD', {
        month: 'short',
        day: 'numeric'
    });

    return `${datePart} · ${timePart}`;
}

function getChatMessageRoleClass(message) {
    return normalizeChatRole(message.sender_role) === 'doctor' ? 'is-doctor' : 'is-patient';
}

function getChatSenderLabel(message) {
    const senderRole = normalizeChatRole(message.sender_role);
    const currentRole = normalizeChatRole(chatCurrentUserRole);

    if (senderRole && senderRole === currentRole) {
        return 'You';
    }

    if (senderRole === 'doctor') {
        return 'Doctor';
    }

    if (senderRole === 'patient') {
        return 'Patient';
    }

    return message.sender_name || 'User';
}

function renderChatLoadingState(text = 'Loading conversation...') {
    return `
        <div class="chat-empty">
            <div class="chat-empty-card">
                <div class="spinner"></div>
                <h3>Loading chat</h3>
                <p>${escapeHtml(text)}</p>
            </div>
        </div>
    `;
}

function syncChatComposerHeight() {
    const input = document.getElementById('chatMessageInput');
    if (!input) {
        return;
    }

    input.style.height = 'auto';
    input.style.height = `${Math.min(Math.max(input.scrollHeight, 52), 136)}px`;
}

function renderChatMessage(message) {
    const roleClass = getChatMessageRoleClass(message);
    const isOwn = normalizeChatRole(message.sender_role) === normalizeChatRole(chatCurrentUserRole);
    const senderLabel = getChatSenderLabel(message);
    const timestamp = formatChatTimestamp(message.created_at);
    const safeTimestamp = escapeHtml(timestamp);

    return `
        <article class="chat-message ${roleClass} ${isOwn ? 'is-self' : ''}" data-message-id="${Number(message.id)}">
            ${renderChatAvatar(message)}
            <div class="chat-message-body">
                <div class="chat-meta">
                    <span class="chat-sender-label">${escapeHtml(senderLabel)}</span>
                    <span class="chat-meta-separator" aria-hidden="true">•</span>
                    <time datetime="${escapeHtml(String(message.created_at || ''))}">${safeTimestamp}</time>
                </div>
                <div class="chat-bubble">${escapeHtml(message.message_text || '')}</div>
            </div>
        </article>
    `;
}

function renderChatEmptyState(text = 'No messages yet. Start the consultation when you are ready.') {
    const feed = document.getElementById('chatFeed');
    if (!feed) {
        return;
    }

    feed.innerHTML = `
        <div class="chat-empty">
            <div class="chat-empty-card">
                <div class="chat-empty-icon" aria-hidden="true">
                    <i class="fas fa-comments"></i>
                </div>
                <h3>No messages yet</h3>
                <p>${escapeHtml(text)}</p>
            </div>
        </div>
    `;
}

function shouldStickChatToBottom() {
    const feed = document.getElementById('chatFeed');
    if (!feed) {
        return true;
    }

    const distanceFromBottom = feed.scrollHeight - feed.scrollTop - feed.clientHeight;
    return distanceFromBottom < 96;
}

function scrollChatToBottom(force = false) {
    const feed = document.getElementById('chatFeed');
    if (!feed) {
        return;
    }

    if (!force && !shouldStickChatToBottom()) {
        return;
    }

    feed.scrollTop = feed.scrollHeight;
}

function appendChatMessages(messages, forceScroll = false) {
    const feed = document.getElementById('chatFeed');
    if (!feed || !Array.isArray(messages) || messages.length === 0) {
        return;
    }

    const shouldScroll = forceScroll || shouldStickChatToBottom();

    const emptyState = feed.querySelector('.chat-empty');
    if (emptyState) {
        feed.innerHTML = '';
    }

    messages.forEach((message) => {
        const exists = feed.querySelector(`[data-message-id="${Number(message.id)}"]`);
        if (exists) {
            return;
        }

        feed.insertAdjacentHTML('beforeend', renderChatMessage(message));
    });

    if (shouldScroll) {
        scrollChatToBottom(true);
    }
}

function updateChatSummary(payload = {}, participants = {}) {
    const summary = document.getElementById('chatSummary');
    const statusBanner = document.getElementById('chatStatusBanner');
    const pageTitle = document.getElementById('chatPageTitle');
    const appointmentStatus = String(payload.status || 'pending');
    const appointmentStatusLabel = appointmentStatus.replace(/_/g, ' ');
    const appointmentDate = payload.appointment_date
        ? formatDate(payload.appointment_date)
        : 'Date pending';
    const appointmentTime = payload.estimated_time
        ? formatTime(payload.estimated_time)
        : 'Time pending';

    if (pageTitle) {
        pageTitle.textContent = `Consultation Chat #${payload.appointment_number || chatAppointmentId || ''}`.trim();
    }

    if (summary) {
        summary.innerHTML = `
            <div class="chat-summary-card">
                <p class="chat-context-label">Appointment</p>
                <p class="chat-context-value">${escapeHtml(payload.appointment_number || 'Not available')}</p>
                <p class="chat-context-meta">${escapeHtml(appointmentDate)} at ${escapeHtml(appointmentTime)}</p>
            </div>
            <div class="chat-summary-card">
                <p class="chat-context-label">Doctor</p>
                <p class="chat-context-value">${escapeHtml(payload.doctor_name || participants.doctor_name || 'Doctor')}</p>
                <p class="chat-context-meta">${escapeHtml(participants.specialty || payload.specialty || 'Consultation doctor')}</p>
            </div>
            <div class="chat-summary-card">
                <p class="chat-context-label">Patient</p>
                <p class="chat-context-value">${escapeHtml(payload.patient_name || participants.patient_name || 'Patient')}</p>
                <p class="chat-context-meta">${escapeHtml(participants.patient_email || 'Patient account')}</p>
            </div>
            <div class="chat-summary-card">
                <p class="chat-context-label">Status</p>
                <div class="chat-context-stack">
                    <div>
                        <span class="status-badge ${escapeHtml(appointmentStatus)}">${escapeHtml(appointmentStatusLabel)}</span>
                    </div>
                    <p class="chat-context-meta">${escapeHtml(payload.clinic_name || 'Clinic information unavailable')}</p>
                </div>
            </div>
        `;
    }

    if (statusBanner) {
        if (chatCanSendMessages) {
            statusBanner.innerHTML = '';
            statusBanner.style.display = 'none';
        } else {
            statusBanner.innerHTML = `
                <div class="chat-status-note">
                    Chat remains visible for reference, but new messages are disabled because this appointment is ${escapeHtml(appointmentStatusLabel)}.
                </div>
            `;
            statusBanner.style.display = 'block';
        }
    }
}

function updateChatComposerState() {
    const input = document.getElementById('chatMessageInput');
    const sendButton = document.getElementById('sendChatMessageButton');
    const composerNote = document.getElementById('chatComposerNote');

    if (!input || !sendButton || !composerNote) {
        return;
    }

    input.disabled = !chatCanSendMessages;
    sendButton.disabled = !chatCanSendMessages;

    if (chatCanSendMessages) {
        input.placeholder = 'Type a consultation message...';
        composerNote.textContent = 'Auto refresh every 4 seconds.';
    } else {
        input.placeholder = 'Messaging is disabled for this appointment status.';
        composerNote.textContent = 'Cancelled and no-show appointments stay read-only in chat.';
    }

    syncChatComposerHeight();
}

async function loadChatConversation(options = {}) {
    const {
        reset = false,
        silent = false
    } = options;

    if (!chatAppointmentId) {
        renderChatEmptyState('Missing appointment ID. Open chat from an appointment card.');
        return;
    }

    if (chatRequestInFlight && !reset) {
        return;
    }

    chatRequestInFlight = true;

    const feed = document.getElementById('chatFeed');
    if (feed && reset) {
        feed.innerHTML = renderChatLoadingState();
    }

    try {
        const params = {};
        if (!reset && chatLastMessageId > 0) {
            params.since_id = chatLastMessageId;
        }

        const result = await API.chats.getConversation(chatAppointmentId, params);
        const messages = result.messages || [];
        const meta = result.meta || {};

        chatCurrentUserRole = meta.current_user_role || Auth.getRole();
        chatCanSendMessages = Boolean(meta.chat_enabled);
        updateChatSummary(result.appointment || {}, result.participants || {});
        updateChatComposerState();

        if (reset) {
            if (messages.length === 0) {
                renderChatEmptyState();
            } else {
                if (feed) {
                    feed.innerHTML = messages.map((message) => renderChatMessage(message)).join('');
                }
                scrollChatToBottom(true);
            }
        } else if (messages.length > 0) {
            appendChatMessages(messages, false);
        }

        chatLastMessageId = Number(meta.last_message_id || chatLastMessageId || 0);
        chatInitialLoadComplete = true;
    } catch (error) {
        console.error('Failed to load appointment chat:', error);

        if (!chatInitialLoadComplete) {
            renderChatEmptyState(error.message || 'Unable to load chat right now.');
        } else if (!silent) {
            showToast(error.message || 'Unable to refresh chat right now.', 'error');
        }
    } finally {
        chatRequestInFlight = false;
    }
}

async function sendChatMessage(event) {
    event.preventDefault();

    const input = document.getElementById('chatMessageInput');
    const sendButton = document.getElementById('sendChatMessageButton');

    if (!input || !sendButton || !chatCanSendMessages) {
        return;
    }

    const message = input.value.trim();
    if (!message) {
        showToast('Please write a message before sending.', 'warning');
        return;
    }

    sendButton.disabled = true;

    try {
        const result = await API.chats.sendMessage(chatAppointmentId, message);
        const sentMessage = result.message || null;

        if (sentMessage) {
            appendChatMessages([sentMessage], true);
            chatLastMessageId = Math.max(chatLastMessageId, Number(sentMessage.id || 0));
        }

        input.value = '';
        syncChatComposerHeight();
        input.focus();
    } catch (error) {
        showToast(error.message || 'Unable to send the message.', 'error');
    } finally {
        sendButton.disabled = !chatCanSendMessages;
    }
}

function startChatPolling() {
    stopChatPolling();

    chatPollingTimer = window.setInterval(() => {
        if (document.hidden) {
            return;
        }

        loadChatConversation({ silent: true });
    }, 4000);
}

function stopChatPolling() {
    if (chatPollingTimer) {
        window.clearInterval(chatPollingTimer);
        chatPollingTimer = null;
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    if (!Auth.requireAuth()) {
        return;
    }

    chatAppointmentId = getChatAppointmentId();

    const backLink = document.getElementById('chatBackLink');
    if (backLink) {
        backLink.href = getChatBackLink();
    }

    const detailLink = document.getElementById('appointmentDetailLink');
    if (detailLink) {
        detailLink.href = getChatBackLink();
    }

    const form = document.getElementById('chatComposerForm');
    if (form) {
        form.addEventListener('submit', sendChatMessage);
    }

    const messageInput = document.getElementById('chatMessageInput');
    if (messageInput) {
        messageInput.addEventListener('input', syncChatComposerHeight);
        syncChatComposerHeight();
    }

    await loadChatConversation({ reset: true });
    startChatPolling();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            loadChatConversation({ silent: true });
        }
    });

    window.addEventListener('beforeunload', stopChatPolling);
});
