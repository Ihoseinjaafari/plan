const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const path = require('path');

const app = express();
const server = http.createServer(app);
const io = new Server(server);

app.use(express.static(path.join(__dirname, 'public')));

// Store room states
const rooms = {};

io.on('connection', (socket) => {
  console.log('User connected:', socket.id);

  socket.on('joinRoom', ({ roomId, username }) => {
    socket.join(roomId);
    
    if (!rooms[roomId]) {
      rooms[roomId] = {
        videoUrl: '',
        currentTime: 0,
        isPlaying: false,
        users: []
      };
    }
    
    rooms[roomId].users.push({ id: socket.id, username });
    
    // Send current room state to the new user
    socket.emit('roomState', rooms[roomId]);
    
    // Notify others in the room
    socket.to(roomId).emit('userJoined', { userId: socket.id, username });
    
    console.log(`${username} joined room ${roomId}`);
  });

  socket.on('setVideoUrl', ({ roomId, videoUrl }) => {
    if (rooms[roomId]) {
      rooms[roomId].videoUrl = videoUrl;
      io.to(roomId).emit('videoUrlChanged', { videoUrl });
    }
  });

  socket.on('playVideo', ({ roomId, time }) => {
    if (rooms[roomId]) {
      rooms[roomId].isPlaying = true;
      rooms[roomId].currentTime = time;
      socket.to(roomId).emit('videoPlay', { time });
    }
  });

  socket.on('pauseVideo', ({ roomId, time }) => {
    if (rooms[roomId]) {
      rooms[roomId].isPlaying = false;
      rooms[roomId].currentTime = time;
      socket.to(roomId).emit('videoPause', { time });
    }
  });

  socket.on('seekVideo', ({ roomId, time }) => {
    if (rooms[roomId]) {
      rooms[roomId].currentTime = time;
      socket.to(roomId).emit('videoSeek', { time });
    }
  });

  socket.on('disconnect', () => {
    console.log('User disconnected:', socket.id);
    
    // Remove user from all rooms
    for (const roomId in rooms) {
      const roomIndex = rooms[roomId].users.findIndex(u => u.id === socket.id);
      if (roomIndex > -1) {
        const user = rooms[roomId].users[roomIndex];
        rooms[roomId].users.splice(roomIndex, 1);
        socket.to(roomId).emit('userLeft', { userId: socket.id, username: user.username });
        
        // Clean up empty rooms
        if (rooms[roomId].users.length === 0) {
          delete rooms[roomId];
        }
      }
    }
  });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});
