<style>
    @keyframes shimmer {
        0%   { background-position: 200% center; }
        100% { background-position: -200% center; }
    }
    /* Mobile actions expansion - inline in row, absolute to prevent horizontal scroll */
    .mobile-actions-active {
        display: flex !important;
        position: absolute;
        right: 110%; /* Place to the left of the trigger button */
        top: 50%;
        transform: translateY(-50%);
        background: rgba(30, 41, 59, 1); /* solid slate-800 to cover text below */
        padding: 0.25rem 0.5rem;
        border-radius: 0.75rem;
        border: 1px solid #334155; /* slate-700 */
        gap: 0.5rem !important;
        z-index: 40;
        box-shadow: -10px 0 20px rgba(15, 23, 42, 0.5);
        white-space: nowrap;
    }
</style>
