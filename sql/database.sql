CREATE DATABASE IF NOT EXISTS student_projects_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_projects_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('student', 'team_leader', 'supervisor', 'jury') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150),
    description TEXT,
    status ENUM('proposé', 'validé', 'en cours', 'en retard', 'livré', 'soutenu', 'clôturé') DEFAULT 'proposé',
    start_date DATE,
    end_date DATE,
    created_by INT,
    supervisor_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (supervisor_id) REFERENCES users(id)
);

CREATE TABLE project_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    user_id INT,
    role_in_project ENUM('leader', 'member'),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    title VARCHAR(150),
    description TEXT,
    assigned_to INT NULL,
    priority ENUM('basse', 'moyenne', 'haute') DEFAULT 'moyenne',
    status ENUM('à faire', 'en cours', 'terminé') DEFAULT 'à faire',
    due_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

CREATE TABLE deliverables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    uploaded_by INT,
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    status ENUM('déposé', 'validé', 'refusé') DEFAULT 'déposé',
    comment TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    evaluator_id INT,
    grade DECIMAL(5,2),
    code_quality INT,
    requirements_respect INT,
    user_interface INT,
    organization INT,
    oral_presentation INT,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluator_id) REFERENCES users(id)
);

-- Données de test (Mot de passe pour tous: 'password')
-- Hash bcrypt de 'password' = $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (first_name, last_name, email, password, role) VALUES
('Jean', 'Dupont', 'student@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Alice', 'Martin', 'leader@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'team_leader'),
('Prof', 'Tournesol', 'prof@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supervisor'),
('Jury', 'Expert', 'jury@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'jury');

INSERT INTO projects (title, description, status, start_date, end_date, created_by, supervisor_id) VALUES
('Plateforme E-learning', 'Site web de cours en ligne', 'en cours', '2024-01-01', '2024-06-30', 2, 3),
('App Mobile Santé', 'Application de suivi médical', 'validé', '2024-02-01', '2024-07-15', 2, 3);

INSERT INTO project_members (project_id, user_id, role_in_project) VALUES
(1, 2, 'leader'), (1, 1, 'member');

INSERT INTO tasks (project_id, title, description, assigned_to, priority, status, due_date, created_by) VALUES
(1, 'Maquette Figma', 'Design des pages', 1, 'haute', 'terminé', '2024-02-15', 2),
(1, 'Base de données', 'Script SQL', 2, 'haute', 'en cours', '2024-03-01', 2),
(1, 'Authentification', 'Login/Register', 1, 'moyenne', 'à faire', '2024-03-15', 2);