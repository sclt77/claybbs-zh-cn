<style id="clay-dark-fix">
/* Global dark-mode hardening. Loaded after page inline styles via topbar. */
html[data-theme="dark"] .btn-light,
html[data-theme="dark"] a.btn-light,
html[data-theme="dark"] button.btn-light,
html[data-theme="dark"] .soft-btn,
html[data-theme="dark"] .wallet-recharge-chip,
html[data-theme="dark"] .currency-chip,
html[data-theme="dark"] .quick-types a,
html[data-theme="dark"] .mode-tabs button,
html[data-theme="dark"] .currency-tabs a,
html[data-theme="dark"] .wallet-link,
html[data-theme="dark"] .growth-tab,
html[data-theme="dark"] .follow-tabs a,
html[data-theme="dark"] .me-tabs a,
html[data-theme="dark"] .me-quick-action,
html[data-theme="dark"] .settings-back,
html[data-theme="dark"] .privacy-back{
  background:#0f172a!important;
  border:1px solid #334155!important;
  color:#cbd5e1!important;
  box-shadow:none!important;
}
html[data-theme="dark"] .btn-light:hover,
html[data-theme="dark"] .soft-btn:hover,
html[data-theme="dark"] .wallet-recharge-chip:hover,
html[data-theme="dark"] .currency-chip:hover,
html[data-theme="dark"] .quick-types a:hover,
html[data-theme="dark"] .mode-tabs button:hover,
html[data-theme="dark"] .currency-tabs a:hover,
html[data-theme="dark"] .wallet-link:hover,
html[data-theme="dark"] .growth-tab:hover,
html[data-theme="dark"] .follow-tabs a:hover,
html[data-theme="dark"] .me-tabs a:hover,
html[data-theme="dark"] .me-quick-action:hover,
html[data-theme="dark"] .settings-back:hover,
html[data-theme="dark"] .privacy-back:hover{
  background:#172033!important;
  border-color:#3b82f6!important;
  color:#dbeafe!important;
}
html[data-theme="dark"] .currency-chip.active,
html[data-theme="dark"] .quick-types a.active,
html[data-theme="dark"] .mode-tabs button.active,
html[data-theme="dark"] .currency-tabs a.active,
html[data-theme="dark"] .growth-tab.active,
html[data-theme="dark"] .follow-tabs a.active,
html[data-theme="dark"] .me-tabs a.active{
  background:#2563eb!important;
  border-color:#60a5fa!important;
  color:#fff!important;
}
html[data-theme="dark"] .btn:not(.btn-light):not(.report-danger):not(.danger-link){
  background:#0284c7!important;
  border-color:#0284c7!important;
  color:#fff!important;
}
html[data-theme="dark"] .btn:not(.btn-light):not(.report-danger):not(.danger-link):hover{background:#0369a1!important;border-color:#0369a1!important;}
html[data-theme="dark"] .card,
html[data-theme="dark"] .recharge-card,
html[data-theme="dark"] .asset-panel,
html[data-theme="dark"] .pay-card,
html[data-theme="dark"] .pay-card section,
html[data-theme="dark"] .order-card,
html[data-theme="dark"] .policy-card,
html[data-theme="dark"] .growth-card,
html[data-theme="dark"] .task-card,
html[data-theme="dark"] .profile-list-card,
html[data-theme="dark"] .settings-section,
html[data-theme="dark"] .privacy-card,
html[data-theme="dark"] .oauth-row,
html[data-theme="dark"] .device-row,
html[data-theme="dark"] .verify-card,
html[data-theme="dark"] .report-card,
html[data-theme="dark"] .report-stat,
html[data-theme="dark"] .follow-list{
  background:#111827!important;
  border-color:#263244!important;
  color:#e5e7eb!important;
  box-shadow:0 10px 30px rgba(0,0,0,.25)!important;
}
html[data-theme="dark"] .recharge-page,
html[data-theme="dark"] .pay-page,
html[data-theme="dark"] .orders-page,
html[data-theme="dark"] .growth-page,
html[data-theme="dark"] .profile-page,
html[data-theme="dark"] .me-page,
html[data-theme="dark"] .settings-page,
html[data-theme="dark"] .privacy-page,
html[data-theme="dark"] .oauth-page,
html[data-theme="dark"] .devices-page,
html[data-theme="dark"] .verify-page,
html[data-theme="dark"] .report-page,
html[data-theme="dark"] .follow-page,
html[data-theme="dark"] .policy-page{
  background:#0f172a!important;
}
html[data-theme="dark"] .asset-current strong,
html[data-theme="dark"] .card-title,
html[data-theme="dark"] .recharge-title,
html[data-theme="dark"] .pay-title,
html[data-theme="dark"] .pay-row strong,
html[data-theme="dark"] .qr-box strong,
html[data-theme="dark"] .orders-title,
html[data-theme="dark"] .order-no,
html[data-theme="dark"] .order-grid strong,
html[data-theme="dark"] .settings-title,
html[data-theme="dark"] .privacy-title,
html[data-theme="dark"] .devices-title,
html[data-theme="dark"] .oauth-title,
html[data-theme="dark"] .verify-title,
html[data-theme="dark"] .growth-title,
html[data-theme="dark"] .follow-title,
html[data-theme="dark"] .report-id,
html[data-theme="dark"] .report-reason,
html[data-theme="dark"] .report-box-title,
html[data-theme="dark"] .wallet-title,
html[data-theme="dark"] .wallet-balance-num,
html[data-theme="dark"] .wallet-balance-name{
  color:#e5e7eb!important;
}
html[data-theme="dark"] .recharge-sub,
html[data-theme="dark"] .card-desc,
html[data-theme="dark"] .notice-list,
html[data-theme="dark"] .asset-current span,
html[data-theme="dark"] .pay-row span,
html[data-theme="dark"] .note,
html[data-theme="dark"] .order-time,
html[data-theme="dark"] .order-grid span,
html[data-theme="dark"] .settings-sub,
html[data-theme="dark"] .settings-desc,
html[data-theme="dark"] .privacy-sub,
html[data-theme="dark"] .privacy-desc,
html[data-theme="dark"] .devices-sub,
html[data-theme="dark"] .device-meta,
html[data-theme="dark"] .oauth-desc,
html[data-theme="dark"] .muted,
html[data-theme="dark"] .report-meta,
html[data-theme="dark"] .report-preview,
html[data-theme="dark"] .report-note,
html[data-theme="dark"] .follow-bio{
  color:#94a3b8!important;
}
html[data-theme="dark"] .empty,
html[data-theme="dark"] .empty-tip,
html[data-theme="dark"] .custom-result,
html[data-theme="dark"] .report-box,
html[data-theme="dark"] .order-grid div,
html[data-theme="dark"] .qr-box,
html[data-theme="dark"] .pay-hint,
html[data-theme="dark"] .task-card,
html[data-theme="dark"] .next-level-box div,
html[data-theme="dark"] .oauth-profile,
html[data-theme="dark"] .device-pill,
html[data-theme="dark"] .type-option,
html[data-theme="dark"] .growth-mini-card,
html[data-theme="dark"] .my-badge-panel{
  background:#0f172a!important;
  border-color:#263244!important;
  color:#cbd5e1!important;
}
html[data-theme="dark"] input,
html[data-theme="dark"] select,
html[data-theme="dark"] textarea,
html[data-theme="dark"] .input,
html[data-theme="dark"] .select,
html[data-theme="dark"] .textarea{
  background:#0f172a!important;
  border-color:#334155!important;
  color:#e5e7eb!important;
}
html[data-theme="dark"] .alert.ok,
html[data-theme="dark"] .alert-success,
html[data-theme="dark"] .privacy-flash,
html[data-theme="dark"] .flash-success{background:#052e1b!important;border-color:#166534!important;color:#86efac!important;}
html[data-theme="dark"] .alert.err,
html[data-theme="dark"] .alert-error,
html[data-theme="dark"] .login-error,
html[data-theme="dark"] .register-error{background:#450a0a!important;border-color:#991b1b!important;color:#fecaca!important;}
html[data-theme="dark"] .status.pending{background:#451a03!important;color:#fcd34d!important;}
html[data-theme="dark"] .status.paid{background:#052e1b!important;color:#86efac!important;}
html[data-theme="dark"] .status.cancelled,
html[data-theme="dark"] .status.failed{background:#450a0a!important;color:#fecaca!important;}
</style>
