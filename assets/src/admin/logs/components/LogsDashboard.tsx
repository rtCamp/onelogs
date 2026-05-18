import React, { useEffect, useState, useCallback } from 'react';
import { createRoot } from 'react-dom/client';
import { __, sprintf } from '@wordpress/i18n';
import { Card, CardBody, CardHeader, Spinner } from '@wordpress/components';
import {
	fetchActions,
	fetchConnectors as apiFetchConnectors,
	fetchContexts as apiFetchContexts,
	fetchLogs as apiFetchLogs,
	fetchSharedSites as apiFetchSharedSites,
	fetchUsers as apiFetchUsers,
} from '../apiService';
import { FiltersPanel } from './FiltersPanel';
import { LogsTable } from './LogsTable';
import { Pagination } from './Pagination';
import type {
	FilterOptions,
	LogEntry,
	SharedSite,
	SortState,
	UserOption,
} from '../types';

const LogsDashboard: React.FC = () => {
	const [logs, setLogs] = useState<LogEntry[]>([]);
	const [contexts, setContexts] = useState<string[]>([]);
	const [actions, setActions] = useState<string[]>([]);
	const [connectors, setConnectors] = useState<string[]>([]);
	const [users, setUsers] = useState<UserOption[]>([]);
	const [sharedSites, setSharedSites] = useState<SharedSite[]>([]);
	const [showSharedSitesLogs, setShowSharedSitesLogs] =
		useState<boolean>(false);
	const [filters, setFilters] = useState<FilterOptions>({
		page: 1,
		per_page: 20,
		current_site_logs: true,
		site_url: 'governing-site',
	});
	const [localSearch, setLocalSearch] = useState<string>('');
	const [loading, setLoading] = useState<boolean>(false);
	const [exportLoading, setExportLoading] = useState<boolean>(false);
	const [totalPages, setTotalPages] = useState<number>(1);
	const [totalLogs, setTotalLogs] = useState<number>(0);
	const [error, setError] = useState<string | null>(null);
	const [currentSort, setCurrentSort] = useState<SortState>({
		field: null,
		direction: null,
	});
	const [showAdvancedFilters, setShowAdvancedFilters] =
		useState<boolean>(true);

	const fetchLogsData = useCallback(async () => {
		setLoading(true);
		setError(null);

		try {
			const result = await apiFetchLogs(filters);
			setLogs(result.logs as LogEntry[]);
			setTotalLogs(result.total);
			setTotalPages(result.pages);

			if (result.errors && result.errors.length > 0) {
				/* translators: %s is replaced with selected site url */
				setError(
					sprintf(
						__('Site %s returned errors:', 'onelogs'),
						filters.site_url
					)
				);
			}
		} catch (err) {
			setError(
				err instanceof Error
					? err.message
					: __('An error occurred while fetching logs', 'onelogs')
			);
		} finally {
			setLoading(false);
		}
	}, [filters]);

	const exportData = async () => {
		setExportLoading(true);
		setError(null);

		try {
			const perPage = 1000; // Adjust based on server capacity
			let page = 1;
			let allLogs: LogEntry[] = [];

			// Clone filters safely.
			const filtersForExport = { ...filters, perPage };

			// Fetch the first page.
			const initialResult = await apiFetchLogs(filtersForExport);
			const totalPagesForExport = initialResult.pages || 1;
			allLogs = [...(initialResult.logs as LogEntry[])];

			// Fetch remaining pages (if any).
			for (page = 2; page <= totalPagesForExport; page++) {
				const pagedFilters = { ...filtersForExport, page, perPage };
				const pageResult = await apiFetchLogs(pagedFilters);
				allLogs.push(...(pageResult.logs as LogEntry[]));
			}

			// Define CSV headers.
			const headers = [
				'Date/Time',
				'User',
				'Role',
				'Action',
				'Object',
				'Details',
				'IP Address',
				'Site Name',
			];

			// Build CSV rows.
			const rows = allLogs.map((log) => [
				log.created || '',
				log.user?.display_name || '',
				log.user_role || '',
				log.action || '',
				log.object_data?.title || '',
				log.summary || '',
				log.ip || '',
				log.site_name || window.location.origin,
			]);

			// Escape double quotes and wrap fields in quotes.
			const escapeCSV = (value: string) =>
				`"${String(value).replace(/"/g, '""')}"`;

			// Combine CSV content.
			const csvContent = [
				headers.map(escapeCSV).join(','),
				...rows.map((row) => row.map(escapeCSV).join(',')),
			].join('\n');

			// Trigger CSV download.
			const blob = new Blob([csvContent], {
				type: 'text/csv;charset=utf-8;',
			});
			const url = URL.createObjectURL(blob);
			const link = document.createElement('a');
			link.href = url;
			link.download = `onelogs-${new Date().toISOString().split('T')[0]}.csv`;
			link.style.display = 'none';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(url);
		} catch (err: unknown) {
			const message =
				err instanceof Error ? err.message : 'Failed to export logs.';
			setError(message);
		} finally {
			setExportLoading(false);
		}
	};

	const loadContexts = useCallback(async () => {
		try {
			const data = await apiFetchContexts(filters);
			setContexts(data);
		} catch (err) {
			setError(__('Error fetching contexts:', 'onelogs'));
		}
	}, [filters]);

	const loadConnectors = useCallback(async () => {
		try {
			const data = await apiFetchConnectors(filters);
			setConnectors(data);
		} catch (err) {
			setError(__('Error fetching connectors:', 'onelogs'));
		}
	}, [filters]);

	const loadUsers = useCallback(async () => {
		try {
			const response = await apiFetchUsers(filters);
			setUsers(response.data as UserOption[]);
		} catch (err) {
			setError(__('Error fetching users:', 'onelogs'));
		}
	}, [filters]);

	const loadSharedSites = useCallback(async () => {
		try {
			const response = await apiFetchSharedSites();
			setSharedSites(response as SharedSite[]);
		} catch (err) {
			setError(__('Error fetching shared sites:', 'onelogs'));
		}
	}, []);

	const loadActions = useCallback(async () => {
		try {
			const data = await fetchActions(filters);
			setActions(data);
		} catch (err) {
			setError(__('Error fetching shared sites:', 'onelogs'));
		}
	}, [filters]);

	useEffect(() => {
		loadContexts();
		loadConnectors();
		loadUsers();
		loadSharedSites();
		loadActions();
		fetchLogsData();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []); // Run only once on mount, do not include fetchLogsData in deps to avoid infinite loop.

	useEffect(() => {
		fetchLogsData();
		loadUsers();
		loadContexts();
		loadActions();
	}, [
		filters,
		showSharedSitesLogs,
		fetchLogsData,
		loadActions,
		loadContexts,
		loadUsers,
	]);

	useEffect(() => {
		setLocalSearch(filters.search || '');
	}, [filters.search]);

	const handleFilterChange = useCallback(
		(key: keyof FilterOptions, value: string | number) => {
			setFilters((prev) => {
				if (value === '' || value === null || value === undefined) {
					delete prev[key];
				} else {
					prev[key] = value;
				}

				return { ...prev, page: 1 };
			});
		},
		[]
	);

	const resetFilters = () => {
		setFilters({
			page: 1,
			per_page: 20,
			current_site_logs: true,
			site_url: 'governing-site',
		});
	};

	const handlePageChange = (newPage: number) => {
		if (newPage >= 1 && newPage <= totalPages) {
			setFilters((prev) => ({
				...prev,
				page: newPage,
			}));
		}
	};

	const handleSort = (field: string) => {
		let newDirection: 'asc' | 'desc' = 'asc';

		if (currentSort.field === field) {
			newDirection = currentSort.direction === 'asc' ? 'desc' : 'asc';
		}

		setCurrentSort({
			field,
			direction: newDirection,
		});

		setFilters((prev) => ({
			...prev,
			orderby: field,
			order: newDirection,
			page: 1,
		}));
	};
	return (
		<Card>
			<CardHeader>
				<div>
					<h1 style={{ margin: 0 }}>
						{__('OneLogs', 'onelogs')}
					</h1>
				</div>
			</CardHeader>
			<CardBody>
				<FiltersPanel
					localSearch={localSearch}
					setLocalSearch={setLocalSearch}
					filters={filters}
					handleFilterChange={handleFilterChange}
					connectors={connectors}
					contexts={contexts}
					actions={actions}
					users={users}
					sharedSites={sharedSites}
					showSharedSitesLogs={showSharedSitesLogs}
					setShowSharedSitesLogs={setShowSharedSitesLogs}
					showAdvancedFilters={showAdvancedFilters}
					setShowAdvancedFilters={setShowAdvancedFilters}
					resetFilters={resetFilters}
					fetchLogsData={fetchLogsData}
					exportData={exportData}
					exportLoading={exportLoading}
				/>
				{error && (
					<Card className="onelogs-error-card">
						<CardBody>
							<p style={{ color: 'red' }}>{error}</p>
						</CardBody>
					</Card>
				)}

				{loading ? (
					<div className="onelogs-spinner-container">
						<Spinner />
					</div>
				) : (
					<div>
						<LogsTable
							logs={logs}
							users={users}
							currentSort={currentSort}
							handleSort={handleSort}
						/>

						<Pagination
							currentPage={filters.page}
							totalPages={totalPages}
							onPageChange={handlePageChange}
							totalLogs={totalLogs}
						/>
					</div>
				)}
			</CardBody>
		</Card>
	);
};

document.addEventListener('DOMContentLoaded', () => {
	const element = document.getElementById('onelogs-logs-dashboard');

	if (element) {
		const root = createRoot(element);
		root.render(<LogsDashboard />);
	}
});

export default LogsDashboard;
