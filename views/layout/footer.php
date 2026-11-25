    </main>

    <!-- Rodapé -->
    <footer class="bg-white border-t border-slate-200 mt-auto py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center text-xs text-slate-500 gap-3">
            <div class="text-center md:text-left space-y-1">
                <div>
                    &copy; 2027 <strong>CRM Soluções em Tecnologia</strong> &bull; <strong>Caio Monteiro</strong> &bull;
                    <span class="font-bold text-indigo-700">DEUS SEJA LOUVADO</span>
                </div>
                <div class="text-[11px] text-slate-400">
                    Para suporte, enviar e-mail para: <a href="mailto:caiormleo@outlook.com.br" class="text-indigo-600 hover:underline font-medium">caiormleo@outlook.com.br</a>
                    &bull; WhatsApp: <strong class="text-slate-600">@caior.monteiro</strong>
                    &bull; GitHub: <a href="https://github.com/chacalbl4ck/reqmaster" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline font-medium">chacalbl4ck/reqmaster</a>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="<?= url('sobre') ?>" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-700 hover:text-indigo-700 font-semibold transition">
                    <i class="fas fa-circle-info"></i> Sobre o Sistema
                </a>
                <span>PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?> &bull; MySQL</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-emerald-50 text-emerald-700 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> Sistema Ativo
                </span>
            </div>
        </div>
    </footer>

    <!-- Modal Genérico de Rejeição de Requisição -->
    <div id="rejectionModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-100">
            <div class="flex items-center gap-3 text-rose-600 mb-4">
                <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center">
                    <i class="fas fa-times-circle text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg text-slate-900">Rejeitar Requisição</h3>
                    <p class="text-xs text-slate-500">Informe o motivo da recusa desta solicitação</p>
                </div>
            </div>

            <form id="rejectionForm" method="POST" action="<?= url('requisicoes/status') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="rejectReqId" value="">
                <input type="hidden" name="status" value="rejeitada">

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Motivo / Justificativa da Rejeição <span class="text-rose-500">*</span></label>
                    <textarea name="reason" id="rejectReason" required rows="4" class="w-full text-sm rounded-lg border-slate-300 focus:border-rose-500 focus:ring-rose-500 shadow-sm" placeholder="Ex: Valor acima do orçamento disponível, especificação técnica incorreta..."></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeRejectionModal()" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition">Cancelar</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-sm transition">Confirmar Rejeição</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts da Aplicação -->
    <script src="<?= asset('js/app.js') ?>"></script>
    <script>
        function openRejectionModal(reqId) {
            document.getElementById('rejectReqId').value = reqId;
            document.getElementById('rejectionModal').classList.remove('hidden');
        }
        function closeRejectionModal() {
            document.getElementById('rejectionModal').classList.add('hidden');
        }
    </script>
</body>
</html>
