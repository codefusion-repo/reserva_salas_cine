USE reserva_salas_cine;

INSERT INTO users (id, name, email, password_hash, role)
VALUES
    (1, 'Admin Local', 'admin@reservacine.local', '$2y$10$6/C2rW57rk8G19yqrc9C6uyPw76kf4U6A4XPEvIw2EfA8U/mtqjHS', 'admin'),
    (2, 'Usuario Demo', 'usuario@reservacine.local', '$2y$10$N4v8WJvNL/8XmnEkFzQ.U.twdxF7kLhl/GS/tptq0wTnGQTvDSEUu', 'user')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    role = VALUES(role);

INSERT INTO rooms (id, name, location, capacity, is_active)
VALUES
    (1, 'Sala Lumiere', 'Primer piso', 72, 1),
    (2, 'Sala Kubrick', 'Segundo piso', 96, 1),
    (3, 'Sala Violeta', 'Segundo piso', 48, 1)
ON DUPLICATE KEY UPDATE
    location = VALUES(location),
    capacity = VALUES(capacity),
    is_active = VALUES(is_active);

INSERT INTO movies (id, title, synopsis, genre, release_year, classification, poster_path, is_active)
VALUES
    (1, 'Orbitas de Medianoche', 'Una tripulacion estudiantil descubre una senal perdida durante una funcion especial de astronomia.', 'Ciencia ficcion', 2026, 'TE+7', 'assets/img/posters/orbitas-de-medianoche.jpg', 1),
    (2, 'La Ultima Funcion', 'Un antiguo cine de barrio prepara su noche final mientras sus trabajadores intentan salvar la cartelera.', 'Drama', 2025, 'TE', 'assets/img/posters/la-ultima-funcion.jpg', 1),
    (3, 'Ritmo en la Ciudad', 'Un grupo de amigos organiza un festival musical que transforma las calles del centro.', 'Musical', 2026, 'TE', 'assets/img/posters/ritmo-en-la-ciudad.jpg', 1),
    (4, 'Bosque Neon', 'Dos hermanas cruzan una reserva iluminada por extranos fenomenos para encontrar a su padre.', 'Aventura', 2024, 'TE+7', 'assets/img/posters/bosque-neon.jpg', 1),
    (5, 'Codigo Fantasma', 'Un equipo de programadores investiga una falla que parece responder desde el pasado.', 'Suspenso', 2025, 'TE+13', 'assets/img/posters/codigo-fantasma.jpg', 1)
ON DUPLICATE KEY UPDATE
    synopsis = VALUES(synopsis),
    genre = VALUES(genre),
    release_year = VALUES(release_year),
    classification = VALUES(classification),
    poster_path = VALUES(poster_path),
    is_active = VALUES(is_active);

INSERT INTO showtimes (id, movie_id, room_id, starts_at, ends_at, format_label, language_label, is_active)
VALUES
    (1, 1, 1, '2026-05-03 15:00:00', '2026-05-03 17:05:00', '2D', 'Subtitulada', 1),
    (2, 1, 2, '2026-05-03 20:30:00', '2026-05-03 22:35:00', 'IMAX', 'Subtitulada', 1),
    (3, 2, 3, '2026-05-04 18:00:00', '2026-05-04 19:50:00', '2D', 'Espanol', 1),
    (4, 3, 1, '2026-05-04 16:15:00', '2026-05-04 18:05:00', '2D', 'Espanol', 1),
    (5, 4, 2, '2026-05-05 19:00:00', '2026-05-05 21:10:00', '3D', 'Doblada', 1),
    (6, 5, 3, '2026-05-05 21:30:00', '2026-05-05 23:20:00', '2D', 'Subtitulada', 1),
    (7, 2, 1, '2026-05-06 14:30:00', '2026-05-06 16:20:00', '2D', 'Espanol', 1)
ON DUPLICATE KEY UPDATE
    movie_id = VALUES(movie_id),
    room_id = VALUES(room_id),
    starts_at = VALUES(starts_at),
    ends_at = VALUES(ends_at),
    format_label = VALUES(format_label),
    language_label = VALUES(language_label),
    is_active = VALUES(is_active);
