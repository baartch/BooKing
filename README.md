# 🎵 BooKing

> **ATTENTION**: This is under development. Don't try this at home!

BooKing is a PHP/MariaDB web application for managing and visualizing venue locations on an interactive map. It features an integrated email client that lets you organize emails into _Conversations_. Each Conversation automatically "cools down" after the last message, helping you easily track which replies you’re still waiting for and keeping your inbox organized.

## 🚀 Setup

Rename `config/config.example.php` to `config/config.php` and update the database credentials and encryption key.

Add cron job for `app/controllers/tasks/taskhandler.php` with a one minute intervall.

## ✨ Features

### Venues

Organize venues in the database and visualize them on the map.

<img width="250" height="auto" alt="venues involo ch_app_pages_map_index php" src="https://github.com/user-attachments/assets/21bd4ec0-bb37-4aa5-aac8-1a2c66ab9b78" /> <img width="250" height="auto" alt="venues_list" src="https://github.com/user-attachments/assets/67075f56-874c-4d64-818e-0231fae124b7" />

Add new venues by simply doing a websearch (Brave Search & Mapbox API Keys required).

<img width="250" height="auto" alt="add venue" src="https://github.com/user-attachments/assets/7a74c300-0fe6-4c98-9b4e-5aa290223a18" />

### Communication

Keep track of your communication with _Conversations_. eMail client integrated.

<img width="250" height="auto" alt="conversations" src="https://github.com/user-attachments/assets/e584c0a3-5013-4dfb-b089-4edd4105c717" /> <img width="250" height="auto" alt="mails" src="https://github.com/user-attachments/assets/87abc4d5-f034-494b-a398-8aefa017f404" />

## 📋 Requirements

- **PHP** 8.0 or higher
- **Web Server**: Apache or Nginx
- **Node.js & npm** (for development only)
- **MariaDB/MySQL**

**Made with ❤️ and AI for musicians and booking agents**
