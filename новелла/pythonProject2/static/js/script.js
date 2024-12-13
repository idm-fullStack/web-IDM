document.addEventListener('DOMContentLoaded', () => {
    const publicChannelList = document.getElementById('public-channel-list');
    const privateChannelList = document.getElementById('private-channel-list');
    const createPublicChannelButton = document.getElementById('create-public-channel');
    const createChannelButton = document.getElementById('create-channel');
    const sendMessageButton = document.getElementById('send-message');
    const messageInput = document.getElementById('message-input');
    const messagesContainer = document.getElementById('messages');
    const channelNameHeader = document.getElementById('channel-name');
    const userList = document.getElementById('user-list');

    let currentChannel = null;

    // Инициализация WebSocket
    const socket = io();

    // Функция для создания публичного канала
    createPublicChannelButton.addEventListener('click', () => {
        const channelName = prompt('Введите название публичного канала:');
        if (channelName) {
            fetch('/create_public_channel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ channel_name: channelName })
            })
            .then(response => response.json())
            .then(data => {
                updatePublicChannelList(data);
            });
        }
    });

    // Функция для создания приватного канала
    createChannelButton.addEventListener('click', () => {
        const channelName = prompt('Введите название приватного канала:');
        if (channelName) {
            fetch('/create_channel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ channel_name: channelName })
            })
            .then(response => response.json())
            .then(data => {
                updatePrivateChannelList(data);
            });
        }
    });

    // Функция для обновления списка публичных каналов
    function updatePublicChannelList(channels) {
        publicChannelList.innerHTML = '';
        channels.forEach(channel => {
            const li = document.createElement('li');
            li.textContent = channel;
            li.setAttribute('data-channel-name', channel);
            li.addEventListener('click', () => {
                currentChannel = channel;
                channelNameHeader.textContent = channel;
                fetchMessages(channel);
            });
            publicChannelList.appendChild(li);
        });
    }

    // Функция для обновления списка приватных каналов
    function updatePrivateChannelList(channels) {
        privateChannelList.innerHTML = '';
        channels.forEach(channel => {
            const li = document.createElement('li');
            li.textContent = channel;
            li.setAttribute('data-channel-name', channel);
            li.addEventListener('click', () => {
                currentChannel = channel;
                channelNameHeader.textContent = channel;
                fetchMessages(channel);
            });
            privateChannelList.appendChild(li);
        });
    }

    // Функция для получения сообщений из канала
    function fetchMessages(channel) {
        fetch(`/get_messages?channel_name=${channel}`)
            .then(response => response.json())
            .then(messages => {
                messagesContainer.innerHTML = '';
                messages.forEach(message => {
                    const p = document.createElement('p');
                    p.textContent = `${message.sender}: ${message.content}`;
                    messagesContainer.appendChild(p);
                });
            });
    }

    // Функция для отправки сообщения
    sendMessageButton.addEventListener('click', () => {
        const message = messageInput.value;
        if (message && currentChannel) {
            socket.emit('send_message', { channel_name: currentChannel, message: message });
            messageInput.value = '';
        }
    });

    // Обработка нового сообщения
    socket.on('new_message', (data) => {
        if (data.channel_name === currentChannel) {
            const p = document.createElement('p');
            p.textContent = `${data.sender}: ${data.message}`;
            messagesContainer.appendChild(p);
        }
    });

    // Обработка приглашения в чат
    userList.addEventListener('click', (event) => {
        if (event.target.classList.contains('invite-button')) {
            const userId = event.target.getAttribute('data-user-id');
            fetch('/invite_to_chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId })
            })
            .then(response => response.json())
            .then(data => {
                updatePrivateChannelList(data);
            });
        }
    });

    // Выбор канала при загрузке страницы
    publicChannelList.addEventListener('click', (event) => {
        if (event.target.tagName === 'LI') {
            const channelName = event.target.getAttribute('data-channel-name');
            currentChannel = channelName;
            channelNameHeader.textContent = channelName;
            fetchMessages(channelName);
        }
    });

    privateChannelList.addEventListener('click', (event) => {
        if (event.target.tagName === 'LI') {
            const channelName = event.target.getAttribute('data-channel-name');
            currentChannel = channelName;
            channelNameHeader.textContent = channelName;
            fetchMessages(channelName);
        }
    });
});