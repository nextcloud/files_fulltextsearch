/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface IAdminSettingsConfig {
	files_local: boolean
	files_external: number
	files_group_folders: boolean
	files_size: number
	files_office: boolean
	files_pdf: boolean
	files_open_result_directly: boolean
}

/**
 * Detail payload of the `fulltextsearch:settings-admin-updated` window event, broadcast by the
 * main fulltextsearch app's admin settings page. See its src/constants.ts for the full contract.
 */
export interface ISettingsUpdatedEventDetail {
	platform: string
	providers: string[]
}
