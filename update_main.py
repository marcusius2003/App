from pathlib import Path
path = Path('index.php')
text = path.read_text(encoding='utf-8')
start = text.index('<main>')
end = text.index('</main>', start) + len('</main>')
new_main = """        <main>
            <section id=\"insight\" class=\"pt-28 pb-10 bg-slate-50\">
                <div class=\"container mx-auto px-6 grid md:grid-cols-3 gap-6 text-center\">
                    <div class=\"bg-white rounded-2xl p-6 border border-gray-200 shadow-sm\">
                        <h3 class=\"text-xl font-semibold text-gray-900 mb-2\">Dolor</h3>
                        <p class=\"text-gray-600 mb-4\">WhatsApp, Excel, correos y silos multiplican incidencias y ruido.</p>
                        <ul class=\"space-y-2 text-left text-sm text-gray-600\">
                            <li>Mensajes dispersos sin trazabilidad.</li>
                            <li>Calendarios y tareas en hojas separadas.</li>
                            <li>Duplicidad de avisos y confusión entre equipos.</li>
                        </ul>
                    </div>
                    <div class=\"bg-white rounded-2xl p-6 border border-gray-200 shadow-sm\">
                        <h3 class=\"text-xl font-semibold text-gray-900 mb-2\">Resultado</h3>
                        <p class=\"text-gray-600 mb-4\">Centralización, trazabilidad, menos incidencias y más tiempo para enseñar.</p>
                        <ul class=\"space-y-2 text-left text-sm text-gray-600\">
                            <li>Canal oficial por roles y categorías.</li>
                            <li>Tareas y calendario sincronizados con recordatorios.</li>
                            <li>Monitorización del día a día desde un panel único.</li>
                        </ul>
                    </div>
                    <div class=\"bg-white rounded-2xl p-6 border border-gray-200 shadow-sm\">
                        <h3 class=\"text-xl font-semibold text-gray-900 mb-2\">Para quién</h3>
                        <p class=\"text-gray-600 mb-4\">Directores, coordinadores, equipos académicos y academias que buscan orden.</p>
                        <ul class=\"space-y-2 text-left text-sm text-gray-600\">
                            <li>Dirección y administración que necesita visibilidad real.</li>
                            <li>Profesorado que exige claridad en tareas y avisos.</li>
                            <li>Alumnado y familias que precisan comunicación oficial única.</li>
                        </ul>
                    </div>
                </div>
            </section>
            <section id=\"home\" class=\"pt-12 pb-20 hero-gradient\">
                <div class=\"container mx-auto px-6 grid md:grid-cols-2 gap-12 items-center hero-content\">
                    <div class=\"text-center md:text-left animate-fadeInUp\">
                        <p class=\"text-sm font-semibold uppercase tracking-widest text-primary mb-3\">Gestión + comunicación + comunidad</p>
                        <h1 class=\"text-4xl md:text-6xl font-extrabold text-gray-900 leading-tight mb-6\">
                            Menos WhatsApp, más control: gestión y comunicación del centro en una sola plataforma.
                        </h1>
                        <p class=\"text-lg text-gray-600 mb-8 max-w-xl mx-auto md:mx-0\">
                            Centraliza tareas, calendario, avisos y comunidad en IUConnect. Implementación guiada y soporte dedicado.
                        </p>
                        <ul class=\"space-y-3 text-left text-gray-700 text-lg max-w-xl mx-auto md:mx-0 mb-8\">
                            <li class=\"flex items-start gap-3\">
                                <span class=\"mt-1 text-primary\"><i class=\"fas fa-check\"></i></span>
                                <span>Comunicación oficial por roles (dirección, profesorado, alumnado).</span>
                            </li>
                            <li class=\"flex items-start gap-3\">
                                <span class=\"mt-1 text-primary\"><i class=\"fas fa-check\"></i></span>
                                <span>Tareas y calendario con recordatorios, trazabilidad y próximas entregas visibles.</span>
                            </li>
                            <li class=\"flex items-start gap-3\">
                                <span class=\"mt-1 text-primary\"><i class=\"fas fa-check\"></i></span>
                                <span>Panel de control para reducir incidencias y duplicidades antes de que lleguen al aula.</span>
                            </li>
                        </ul>
                        <div class=\"flex flex-col sm:flex-row justify-center md:justify-start gap-4\">
                            <a href=\"#contact\" class=\"bg-gradient-primary text-white px-8 py-3.5 rounded-lg font-semibold text-lg shadow-lg hover:shadow-xl transition-all hover-lift\">
                                Ver demo en 15 min
                            </a>
                            <a href=\"#modules\" class=\"bg-white text-gray-700 px-8 py-3.5 rounded-lg font-semibold text-lg border border-gray-300 shadow-sm hover:bg-gray-50 hover:shadow-md transition-all\">
                                Ver módulos
                            </a>
                        </div>
                    </div>
                    <div class=\"mt-10 md:mt-0 animate-fadeInUp floating-element\" style=\"animation-delay: 0.2s;\">
                        <div class=\"relative\">
                            <div class=\"absolute -inset-4 bg-gradient-primary rounded-2xl opacity-10 blur\"></div>
                            <div class=\"relative rounded-xl shadow-2xl border-4 border-white bg-gradient-to-br from-gray-100 to-gray-200 p-8\" role=\"img\" aria-label=\"Vista previa del Dashboard de IUConnect mostrando métricas y estadísticas\">
                                <div class=\"flex items-center justify-between mb-6\">
                                    <div class=\"flex items-center\">
                                        <div class=\"w-3 h-3 rounded-full bg-red-500 mr-2\"></div>
                                        <div class=\"w-3 h-3 rounded-full bg-yellow-500 mr-2\"></div>
                                        <div class=\"w-3 h-3 rounded-full bg-green-500\"></div>
                                    </div>
                                    <div class=\"text-sm font-medium text-gray-700\">IUConnect Dashboard</div>
                                    <div class=\"w-6 h-6 rounded-full bg-gray-300\"></div>
                                </div>
                                <div class=\"grid grid-cols-3 gap-4 mb-4\">
                                    <div class=\"bg-white rounded-lg p-3 shadow-sm\">
                                        <div class=\"h-2 bg-gray-200 rounded mb-2\"></div>
                                        <div class=\"h-2 bg-gray-200 rounded w-3/4\"></div>
                                    </div>
                                    <div class=\"bg-white rounded-lg p-3 shadow-sm\">
                                        <div class=\"h-2 bg-gray-200 rounded mb-2\"></div>
                                        <div class=\"h-2 bg-gray-200 rounded w-3/4\"></div>
                                    </div>
                                    <div class=\"bg-white rounded-lg p-3 shadow-sm\">
                                        <div class=\"h-2 bg-gray-200 rounded mb-2\"></div>
                                        <div class=\"h-2 bg-gray-200 rounded w-3/4\"></div>
                                    </div>
                                </div>
                                <div class=\"bg-white rounded-lg p-4 shadow-sm\">
                                    <div class=\"flex justify-between items-center mb-3\">
                                        <div class=\"h-3 bg-gray-200 rounded w-1/4\"></div>
                                        <div class=\"h-3 bg-gray-200 rounded w-1/6\"></div>
                                    </div>
                                    <div class=\"space-y-2\">
                                        <div class=\"h-2 bg-gray-200 rounded\"></div>
                                        <div class=\"h-2 bg-gray-200 rounded w-5/6\"></div>
                                        <div class=\"h-2 bg-gray-200 rounded w-4/6\"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section id=\"problema\" class=\"py-20 bg-white\">
                <div class=\"container mx-auto px-6\">
                    <div class=\"text-center max-w-3xl mx-auto mb-12\">
                        <h2 class=\"text-3xl md:text-4xl font-bold text-gray-900 mb-4\">Plataforma de gestión escolar</h2>
                        <p class=\"text-lg text-gray-600\">IUConnect organiza el flujo diario para que cada persona sepa qué hacer y cuándo.</p>
                    </div>
                    <div class=\"grid md:grid-cols-2 gap-8\">
                        <div class=\"bg-gray-50 border border-gray-200 rounded-2xl p-8\">
                            <h3 class=\"text-xl font-semibold text-gray-900 mb-4\">Antes (sin IUConnect)</h3>
                            <ul class=\"space-y-3 text-gray-600\">
                                <li>Avisos desperdigados (WhatsApp/correos).</li>
                                <li>Tareas sin control y recordatorios manuales.</li>
                                <li>Información duplicada y dudas constantes.</li>
                                <li>Falta de trazabilidad y cierre de incidencias.</li>
                            </ul>
                        </div>
                        <div class=\"bg-white border border-primary/20 shadow-sm rounded-2xl p-8\">
                            <h3 class=\"text-xl font-semibold text-gray-900 mb-4\">Después (con IUConnect)</h3>
                            <ul class=\"space-y-3 text-gray-600\">
                                <li>Canal oficial por roles y categorías con mensajes destacados.</li>
                                <li>Calendario + tareas sincronizadas con recordatorios automáticos.</li>
                                <li>Menos incidencias y más trazabilidad gracias a paneles y auditoría.</li>
                                <li>Visión compartida que agiliza decisiones y reduce retrabajo.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
            <section id=\"roles\" class=\"py-20 bg-gray-50\">
                <div class=\"container mx-auto px-6\">
                    <div class=\"text-center max-w-3xl mx-auto mb-12\">
                        <h2 class=\"text-3xl md:text-4xl font-bold text-gray-900 mb-4\">Beneficios por rol</h2>
                        <p class=\"text-lg text-gray-600\">Cada perfil encuentra en IUConnect lo que necesita para avanzar sin fricciones.</p>
                    </div>
                    <div class=\"grid md:grid-cols-3 gap-8\">
                        <div class=\"bg-white rounded-2xl border border-gray-200 p-6 shadow-sm\">
                            <h3 class=\"text-xl font-semibold text-gray-900 mb-4\">Dirección / Administración</h3>
                            <ul class=\"space-y-3 text-gray-600\">
                                <li>Visibilidad global del centro con métricas que simplifican informes.</li>
                                <li>Control de permisos y auditoría sobre comunicaciones sensibles.</li>
                                <li>Menos carga operativa gracias a automatizaciones seguras.</li>
                            </ul>
                        </div>
                        <div class=\"bg-white rounded-2xl border border-gray-200 p-6 shadow-sm\">
                            <h3 class=\"text-xl font-semibold text-gray-900 mb-4\">Profesorado</h3>
                            <ul class=\"space-y-3 text-gray-600\">
                                <li>Menos tiempo organizando, más enseñando.</li>
                                <li>Tareas y eventos claros y compartidos con alumnado y familias.</li>
                                <li>Comunicación ordenada y contextualizada por grupos.</li>
                            </ul>
                        </div>
                        <div class=\"bg-white rounded-2xl border border-gray-200 p-6 shadow-sm\">
                            <h3 class=\"text-xl font-semibold text-gray-900 mb-4\">Alumnado / Familias</h3>
                            <ul class=\"space-y-3 text-gray-600\">
                                <li>Información centralizada y oficial.</li>
                                <li>Recordatorios y avisos que llegan a tiempo.</li>
                                <li>Menos confusión y mayor confianza en los canales del centro.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
            <section id=\"modules\" class=\"py-20 bg-white\">
                <div class=\"container mx-auto px-6\">
                    <div class=\"text-center max-w-3xl mx-auto mb-12\">
                        <h2 class=\"text-3xl md:text-4xl font-bold text-gray-900 mb-4\">Software de comunicación para colegios</h2>
                        <p class=\"text-lg text-gray-600\">Agrupamos herramientas esenciales en cuatro bloques que dialogan entre sí.</p>
                    </div>
                    <div class=\"grid md:grid-cols-2 lg:grid-cols-4 gap-6\">
                        <div class=\"bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm\">
                            <h3 class=\"text-xl font-semibold text-gray-900 mb-3\">Gestión</h3>
                            <p class=\"text-sm text-gray-500 mb-4\">Usuarios, roles y estructura académica.</p>
                            <ul class=\"space-y-2 text-gray-600 text-sm\">
                                <li>Altas, bajas y sincronización con ERP del centro.</li>
                                <li>Roles detallados y permisos por perfil.</li>
                                <li>Configuración de cursos, ciclos y grupos.</li>
                            </ul>
                        </div>
                        <div class=\"bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm\">
                            <h3 class=\"text-xl font-semibold text-gray-900 mb-3\">Operativa</h3>
                            <p class=\"text-sm text-gray-500 mb-4\">Tareas, calendario y recordatorios.</p>
                            <ul class=\"space-y-2 text-gray-600 text-sm\">
                                <li>Tareas vinculadas a grupos y competencias.</li>
                                <li>Calendario único con sincronizaciones automáticas.</li>
                                <li>Recordatorios por roles y trazabilidad de entregas.</li>
                            </ul>
                        </div>
                        <div class=\"bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm\">
                            <h3 class=\"text-xl font-semibold text-gray-900 mb-3\">Comunidad</h3>
                            <p class=\"text-sm text-gray-500 mb-4\">Muro y chats con contexto educativo.</p>
                            <ul class=\"space-y-2 text-gray-600 text-sm\">
                                <li>Muro segmentado por ciclos o departamentos.</li>
                                <li>Chats internos moderados por roles.</li>
                                <li>Encuestas y anuncios con confirmación de lectura.</li>
                            </ul>
                        </div>
                        <div class=\"bg-gray-50 border border-gray-200 rounded-2xl p-6 shadow-sm\">
                            <h3 class=\"text-xl font-semibold text-gray-900 mb-3\">Soporte</h3>
                            <p class=\"text-sm text-gray-500 mb-4\">Tickets y ayuda guiada.</p>
                            <ul class=\"space-y-2 text-gray-600 text-sm\">
                                <li>Canal de tickets con categorías y seguimiento.</li>
                                <li>Base de conocimiento interna actualizada.</li>
                                <li>Escalado inmediato hacia soporte técnico dedicado.</li>
                            </ul>
                        </div>
                    </div>
                    <div class=\"mt-12 text-center max-w-3xl mx-auto\">
                        <h2 class=\"text-3xl font-bold text-gray-900 mb-3\">Agenda digital escolar y tareas</h2>
                        <p class=\"text-lg text-gray-600\">Vinculamos cada tarea con el calendario y los avisos oficiales para que el centro funcione sin duplicidades ni incertidumbre.</p>
                    </div>
                </div>
            </section>
            <section id=\"security\" class=\"py-20 bg-gray-50\">
                <div class=\"container mx-auto px-6\">
                    <div class=\"text-center max-w-3xl mx-auto mb-12\">
                        <h2 class=\"text-3xl md:text-4xl font-bold text-gray-900 mb-4\">Seguridad y control</h2>
                        <p class=\"text-lg text-gray-600\">Trazabilidad, roles y RGPD son parte del núcleo técnico de IUConnect.</p>
                    </div>
                    <div class=\"grid md:grid-cols-2 gap-6\">
                        <div class=\"bg-white border border-gray-200 rounded-2xl p-6 shadow-sm\">
                            <h3 class=\"text-lg font-semibold text-gray-900 mb-3\">Roles y permisos por perfil</h3>
                            <p class=\"text-gray-600\">Asignamos accesos granulares para dirección, profesorado, familias y alumnado; cada acción queda registrada.</p>
                        </div>
                        <div class=\"bg-white border border-gray-200 rounded-2xl p-6 shadow-sm\">
                            <h3 class=\"text-lg font-semibold text-gray-900 mb-3\">Registro de actividad y trazabilidad</h3>
                            <p class=\"text-gray-600\">Seguimos cada mensaje, tarea y cambio con auditoría automática para evidenciar las decisiones.</p>
                        </div>
                        <div class=\"bg-white border border-gray-200 rounded-2xl p-6 shadow-sm\">
                            <h3 class=\"text-lg font-semibold text-gray-900 mb-3\">Protección contra accesos indebidos</h3>
                            <p class=\"text-gray-600\">Revisamos intentos fallidos, forzamos doble factor y actualizamos certificaciones para mantener la plataforma blindada.</p>
                        </div>
                        <div class=\"bg-white border border-gray-200 rounded-2xl p-6 shadow-sm\">
                            <h3 class=\"text-lg font-semibold text-gray-900 mb-3\">Cumplimiento RGPD</h3>
                            <p class=\"text-gray-600\">Los datos residen en la UE y tratamos la información con base legal clara; <a href=\"#privacy-policy\" class=\"text-primary font-semibold\">consulta la política de privacidad</a>.</p>
                        </div>
                    </div>
                    <div id=\"privacy-policy\" class=\"mt-8 bg-white rounded-2xl border border-gray-200 p-6 text-sm text-gray-600 space-y-3\">
                        <h3 class=\"text-lg font-semibold text-gray-900\">Política de privacidad</h3>
                        <p>Tratamos los datos de contacto y académicos únicamente para la prestación del servicio solicitado y con las bases legales previstas en el RGPD.</p>
                        <p>Los registros de actividad y los backups están cifrados y alojados en centros de datos ubicados en territorio europeo.</p>
                        <p>Las personas responsables pueden solicitar acceso, rectificación o supresión a través de <a href=\"mailto:info@iuconnect.net\" class=\"text-primary font-semibold\">info@iuconnect.net</a>.</p>
                    </div>
                </div>
            </section>
            <section id=\"process\" class=\"py-20 bg-white\">
                <div class=\"container mx-auto px-6\">
                    <div class=\"text-center max-w-3xl mx-auto mb-12\">
                        <h2 class=\"text-3xl md:text-4xl font-bold text-gray-900 mb-4\">Proceso de implementación</h2>
                        <p class=\"text-lg text-gray-600\">3 a 5 semanas de trabajo conjunto para arrancar con el flujo completo.</p>
                    </div>
                    <div class=\"relative max-w-6xl mx-auto\">
                        <div class=\"hidden md:block absolute top-10 left-0 w-full border-t border-dashed border-gray-300\" style=\"z-index: 1;\"></div>
                        <div class=\"relative grid grid-cols-1 md:grid-cols-4 gap-8 text-center\" style=\"z-index: 2;\">
                            <div class=\"flex flex-col items-center bg-gray-50 p-6 rounded-2xl border border-gray-200 shadow-sm\">
                                <div class=\"w-16 h-16 bg-gradient-primary text-white rounded-full flex items-center justify-center text-2xl font-bold mb-4\">1</div>
                                <h3 class=\"text-xl font-semibold text-gray-900 mb-2\">Diagnóstico y demo (1 semana)</h3>
                                <p class=\"text-gray-600\">Analizamos necesidades, áreas críticas y definimos el alcance técnico y pedagógico.</p>
                            </div>
                            <div class=\"flex flex-col items-center bg-gray-50 p-6 rounded-2xl border border-gray-200 shadow-sm\">
                                <div class=\"w-16 h-16 bg-gradient-primary text-white rounded-full flex items-center justify-center text-2xl font-bold mb-4\">2</div>
                                <h3 class=\"text-xl font-semibold text-gray-900 mb-2\">Planificación y kickoff (1 semana)</h3>
                                <p class=\"text-gray-600\">Creamos rutas de trabajo, asignamos responsables y presentamos el plan de comunicación interna.</p>
                            </div>
@@
