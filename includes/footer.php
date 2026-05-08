<script>
function toggleSidebar() {
    const s = document.getElementById('sidebar');
    if (s) s.classList.toggle('sidebar-collapsed');
}
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'bi bi-eye-slash text-sm' : 'bi bi-eye text-sm';
}
</script>
</body>
</html>
