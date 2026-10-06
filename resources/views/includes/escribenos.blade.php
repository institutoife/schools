
{{-- %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%  contactar %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%% --}}

<!-- Contacto Section -->
<section id="contacto" class="contacto-section">
    <div class="container">
        <div class="contacto-grid">
            <div class="contacto-info">
                <div class="section-header">
                    <h2>Contáctanos</h2>
                    <p>¿Tienes preguntas? Estamos aquí para ayudarte</p>
                </div>
                
                <div class="info-items">
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h4>Dirección</h4>
                            <p>Villa 1 de mayo, calle 16 oeste #9</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h4>Teléfono</h4>
                            <p>+59160902299</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h4>Email</h4>
                            <p>{{ config('brand.contact_email') }}</p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h4>Horario</h4>
                            <p>Lunes a Sábado: 7:30 am - 06:30pm</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="contacto-form">
                <form onsubmit="enviarWhatsApp(event)" method="POST">
                    @csrf
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" required>
                    </div>
                    <div class="form-group">
                        <label for="mensaje">Mensaje</label>
                        <textarea id="mensaje" name="mensaje" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar a WhatsApp</button>
                </form>
            </div>
        </div>
    </div>
</section>
{{-- %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%  contactar %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%% --}}

