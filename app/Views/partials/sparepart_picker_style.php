<style>
    /* Pemilih sparepart — daftar pilih, bukan tabel padat */
    .service-sp-search {
        position: relative;
    }

    .service-sp-search-icon {
        position: absolute;
        top: 50%;
        left: 14px;
        transform: translateY(-50%);
        color: var(--bs-secondary-color);
        pointer-events: none;
    }

    .service-sp-search .form-control {
        height: 44px;
        padding-left: 42px;
    }

    .service-sp-unit {
        flex-shrink: 0;
        font-size: .75rem;
        font-weight: 600;
        color: var(--bs-secondary-color);
    }

    .service-sp-list {
        display: flex;
        flex-direction: column;
        gap: .5rem;
        min-height: 220px;
        max-height: min(58vh, 460px);
        overflow-y: auto;
        padding: 4px;
        scrollbar-width: thin;
        scrollbar-color: var(--bs-border-color) transparent;
    }

    .service-sp-item {
        position: relative;
        margin: 0;
        cursor: pointer;
    }

    .service-sp-item__input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .service-sp-item__box {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .7rem .875rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        background: var(--bs-body-bg);
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
    }

    .service-sp-item:hover .service-sp-item__box {
        border-color: var(--bs-primary-border-subtle);
        background: var(--bs-primary-bg-subtle);
    }

    .service-sp-item__input:focus-visible+.service-sp-item__box {
        outline: 2px solid var(--bs-primary);
        outline-offset: 2px;
    }

    .service-sp-item__input:checked+.service-sp-item__box {
        border-color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
    }

    .service-sp-tick {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border: 1.5px solid var(--bs-border-color);
        border-radius: 50%;
        color: transparent;
        transition: border-color .15s ease, background-color .15s ease, color .15s ease;
    }

    .service-sp-item__input:checked+.service-sp-item__box .service-sp-tick {
        border-color: var(--bs-primary);
        background: var(--bs-primary);
        color: #fff;
    }

    .service-sp-item__main {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .2rem;
    }

    .service-sp-name {
        font-weight: 600;
        color: var(--bs-heading-color);
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .service-sp-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .1rem .6rem;
        font-size: .78rem;
        color: var(--bs-secondary-color);
    }

    .service-sp-meta-item {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        min-width: 0;
    }

    .service-sp-meta-item i {
        font-size: .85rem;
        flex: 0 0 auto;
    }

    .service-sp-code {
        font-weight: 600;
        color: var(--bs-body-color);
        font-variant-numeric: tabular-nums;
    }

    .service-sp-side {
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: .3rem;
        text-align: right;
    }

    .service-sp-price {
        font-weight: 700;
        color: var(--bs-heading-color);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .service-sp-stock {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .1rem .5rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 999px;
        background: var(--bs-tertiary-bg);
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .service-sp-stock.is-low {
        border-color: var(--bs-warning-border-subtle);
        background: var(--bs-warning-bg-subtle);
        color: var(--bs-warning-text-emphasis);
    }

    .service-sp-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        min-height: 220px;
        padding: 2rem 1rem;
        text-align: center;
    }

    .service-sp-state i {
        font-size: 1.4rem;
        color: var(--bs-secondary-color);
    }

    .service-sp-state p {
        margin: 0;
        font-size: .82rem;
        color: var(--bs-secondary-color);
    }

    .service-sp-state.is-error i {
        color: var(--bs-danger);
    }

    .service-sp-count {
        font-size: .82rem;
        color: var(--bs-secondary-color);
        font-variant-numeric: tabular-nums;
    }

    @media (max-width: 575.98px) {
        .service-sp-item__box {
            flex-wrap: wrap;
        }

        .service-sp-side {
            flex-basis: 100%;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            padding-left: calc(22px + .75rem);
        }
    }
</style>
