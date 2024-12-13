from flask import Flask, render_template, request, jsonify, redirect, url_for, flash
from flask_socketio import SocketIO, emit
from flask_login import LoginManager, UserMixin, login_user, login_required, logout_user, current_user
from flask_wtf import FlaskForm
from wtforms import StringField, PasswordField, SubmitField
from wtforms.validators import DataRequired, EqualTo, ValidationError
from werkzeug.security import generate_password_hash, check_password_hash
from flask_sqlalchemy import SQLAlchemy
from flask_migrate import Migrate
import subprocess

app = Flask(__name__)
app.config['SECRET_KEY'] = 'your_secret_key'
app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///chat.db'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False

socketio = SocketIO(app)
login_manager = LoginManager(app)
login_manager.login_view = 'login'

db = SQLAlchemy(app)
migrate = Migrate(app, db)


# Модель пользователя
class User(UserMixin, db.Model):
    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(80), unique=True, nullable=False)
    password_hash = db.Column(db.String(128), nullable=False)

    def check_password(self, password):
        return check_password_hash(self.password_hash, password)


# Модель канала
class Channel(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(80), unique=True, nullable=False)
    members = db.Column(db.String(500), nullable=False)  # Список участников канала
    is_public = db.Column(db.Boolean, default=False)  # Флаг для публичных каналов


# Модель сообщения
class Message(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    channel_id = db.Column(db.Integer, db.ForeignKey('channel.id'), nullable=False)
    sender_id = db.Column(db.Integer, db.ForeignKey('user.id'), nullable=False)
    content = db.Column(db.String(500), nullable=False)


# Лоадер пользователя для Flask-Login
@login_manager.user_loader
def load_user(user_id):
    return User.query.get(int(user_id))


# Форма регистрации
class RegistrationForm(FlaskForm):
    username = StringField('Username', validators=[DataRequired()])
    password = PasswordField('Password', validators=[DataRequired()])
    confirm_password = PasswordField('Confirm Password', validators=[DataRequired(), EqualTo('password')])
    submit = SubmitField('Register')

    def validate_username(self, username):
        user = User.query.filter_by(username=username.data).first()
        if user:
            raise ValidationError('Username already exists.')


# Форма входа
class LoginForm(FlaskForm):
    username = StringField('Username', validators=[DataRequired()])
    password = PasswordField('Password', validators=[DataRequired()])
    submit = SubmitField('Login')


# Регистрация пользователя
@app.route('/register', methods=['GET', 'POST'])
def register():
    form = RegistrationForm()
    if form.validate_on_submit():
        hashed_password = generate_password_hash(form.password.data, method='pbkdf2:sha256')
        new_user = User(username=form.username.data, password_hash=hashed_password)
        db.session.add(new_user)
        db.session.commit()
        flash('Registration successful. You can now log in.', 'success')
        return redirect(url_for('login'))
    return render_template('register.html', form=form)


# Вход пользователя
@app.route('/login', methods=['GET', 'POST'])
def login():
    form = LoginForm()
    if form.validate_on_submit():
        user = User.query.filter_by(username=form.username.data).first()
        if user and user.check_password(form.password.data):
            login_user(user)
            return redirect(url_for('index'))
        else:
            flash('Invalid username or password.', 'danger')
    return render_template('login.html', form=form)


# Выход пользователя
@app.route('/logout')
@login_required
def logout():
    logout_user()
    return redirect(url_for('login'))


# Главная страница
@app.route('/')
@login_required
def index():
    public_channels = Channel.query.filter_by(is_public=True).all()
    private_channels = Channel.query.filter(Channel.members.contains(current_user.username)).all()
    users = User.query.all()
    return render_template('index.html', current_user=current_user, public_channels=public_channels, private_channels=private_channels, users=users)


# Создание публичного канала
@app.route('/create_public_channel', methods=['POST'])
@login_required
def create_public_channel():
    channel_name = request.json.get('channel_name')
    if not Channel.query.filter_by(name=channel_name).first():
        new_channel = Channel(name=channel_name, members=current_user.username, is_public=True)
        db.session.add(new_channel)
        db.session.commit()
    return jsonify([channel.name for channel in Channel.query.filter_by(is_public=True).all()])


# Создание приватного канала
@app.route('/create_channel', methods=['POST'])
@login_required
def create_channel():
    channel_name = request.json.get('channel_name')
    if not Channel.query.filter_by(name=channel_name).first():
        new_channel = Channel(name=channel_name, members=current_user.username)
        db.session.add(new_channel)
        db.session.commit()
    return jsonify([channel.name for channel in Channel.query.filter(Channel.members.contains(current_user.username)).all()])


# Обработка отправки сообщения через WebSocket
@socketio.on('send_message')
@login_required
def handle_message(data):
    channel_name = data['channel_name']
    message = data['message']
    channel = Channel.query.filter_by(name=channel_name).first()
    if channel and (channel.is_public or current_user.username in channel.members.split(',')):
        new_message = Message(channel_id=channel.id, sender_id=current_user.id, content=message)
        db.session.add(new_message)
        db.session.commit()
        emit('new_message', {'channel_name': channel_name, 'message': message, 'sender': current_user.username}, broadcast=True)


# Получение сообщений из канала
@app.route('/get_messages', methods=['GET'])
@login_required
def get_messages():
    channel_name = request.args.get('channel_name')
    channel = Channel.query.filter_by(name=channel_name).first()
    if channel and (channel.is_public or current_user.username in channel.members.split(',')):
        messages = Message.query.filter_by(channel_id=channel.id).all()
        return jsonify([{'sender': User.query.get(message.sender_id).username, 'content': message.content} for message in messages])
    return jsonify([])


# Приглашение пользователя в приватный чат
@app.route('/invite_to_chat', methods=['POST'])
@login_required
def invite_to_chat():
    invited_user_id = request.json.get('user_id')
    invited_user = User.query.get(invited_user_id)
    if invited_user:
        channel_name = f"{current_user.username}-{invited_user.username}"
        if not Channel.query.filter_by(name=channel_name).first():
            new_channel = Channel(name=channel_name, members=f"{current_user.username},{invited_user.username}")
            db.session.add(new_channel)
            db.session.commit()
    return jsonify([channel.name for channel in Channel.query.filter(Channel.members.contains(current_user.username)).all()])


if __name__ == '__main__':
    with app.app_context():
        db.create_all()
        subprocess.run(['flask', 'db', 'upgrade'], check=True)
    socketio.run(app, debug=True)