<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		v-show="visible"
		:name="t('files_fulltextsearch', 'Files')">
		<h3>{{ t('files_fulltextsearch', 'Sources') }}</h3>
		<NcFormBox>
			<NcFormBoxSwitch
				:modelValue="config.files_local"
				:label="t('files_fulltextsearch', 'Local Files')"
				:description="t('files_fulltextsearch', 'Index the content of local files.')"
				@update:modelValue="saveSettings({ files_local: $event })" />

			<NcSelect
				:modelValue="selectedExternalOption"
				:inputLabel="t('files_fulltextsearch', 'External Files')"
				:options="externalOptions"
				label="label"
				:clearable="false"
				@update:modelValue="(option) => saveSettings({ files_external: option.id })" />

			<NcFormBoxSwitch
				:modelValue="config.files_group_folders"
				:label="t('files_fulltextsearch', 'Group Folders')"
				:description="t('files_fulltextsearch', 'Index the content of group folders.')"
				@update:modelValue="saveSettings({ files_group_folders: $event })" />
		</NcFormBox>

		<h3>{{ t('files_fulltextsearch', 'Types') }}</h3>
		<NcFormBox>
			<NcInputField
				:modelValue="config.files_size"
				:label="t('files_fulltextsearch', 'Maximum file size')"
				:helperText="t('files_fulltextsearch', 'Maximum file size to index (in Mb).')"
				type="number"
				min="0"
				@update:modelValue="(value) => saveSettings({ files_size: Number(value) ?? 0 })" />

			<NcFormBoxSwitch
				:modelValue="config.files_pdf"
				:label="t('files_fulltextsearch', 'Extract PDF')"
				:description="t('files_fulltextsearch', 'Index the content of PDF files.')"
				@update:modelValue="saveSettings({ files_pdf: $event })" />

			<NcFormBoxSwitch
				:modelValue="config.files_office"
				:label="t('files_fulltextsearch', 'Extract Office')"
				:description="t('files_fulltextsearch', 'Index the content of office files.')"
				@update:modelValue="saveSettings({ files_office: $event })" />
		</NcFormBox>

		<h3>{{ t('files_fulltextsearch', 'Results') }}</h3>
		<NcFormBox>
			<NcFormBoxSwitch
				:modelValue="config.files_open_result_directly"
				:label="t('files_fulltextsearch', 'Open Files')"
				:description="t('files_fulltextsearch', 'Directly from search results.')"
				@update:modelValue="saveSettings({ files_open_result_directly: $event })" />
		</NcFormBox>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import type { IAdminSettingsConfig, ISettingsUpdatedEventDetail } from './types.d.ts'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import { FILES_PROVIDER_ID, SETTINGS_UPDATED_EVENT } from './constants.ts'
import { logger } from './logger.ts'

interface ISelectOption {
	id: number
	label: string
}

const config = ref(loadState<IAdminSettingsConfig>('files_fulltextsearch', 'adminConfig'))
const visible = ref(window.OCA?.FullTextSearch?.settings?.providers?.includes(FILES_PROVIDER_ID) ?? false)

const externalOptions: ISelectOption[] = [
	{ id: 0, label: t('files_fulltextsearch', 'Index path only') },
	{ id: 1, label: t('files_fulltextsearch', 'Index path and content') },
	{ id: 2, label: t('files_fulltextsearch', 'Do not index path nor content') },
]

const selectedExternalOption = computed<ISelectOption | null>(() => externalOptions
	.find((option) => option.id === config.value.files_external) ?? null)

/**
 * Show or hide this section based on the platform/provider selection broadcast by the main
 * fulltextsearch app's admin settings page.
 *
 * @param detail Event detail, or the value of window.OCA.FullTextSearch.settings.
 */
function onSettingsUpdated(detail: ISettingsUpdatedEventDetail): void {
	visible.value = detail.providers.includes(FILES_PROVIDER_ID)
}

/**
 * @param event The fulltextsearch:settings-admin-updated CustomEvent.
 */
function handleSettingsUpdatedEvent(event: Event): void {
	onSettingsUpdated((event as CustomEvent<ISettingsUpdatedEventDetail>).detail)
}

onMounted(() => {
	window.addEventListener(SETTINGS_UPDATED_EVENT, handleSettingsUpdatedEvent)
})

onBeforeUnmount(() => {
	window.removeEventListener(SETTINGS_UPDATED_EVENT, handleSettingsUpdatedEvent)
})

/**
 * Persist a settings change on the backend and refresh local state from the response.
 *
 * @param patch Partial config values to change before saving.
 */
async function saveSettings(patch: Partial<IAdminSettingsConfig>): Promise<void> {
	Object.assign(config.value, patch)

	try {
		const { data } = await axios.post<IAdminSettingsConfig>(generateUrl('/apps/files_fulltextsearch/admin/settings'), {
			data: {
				files_local: config.value.files_local ? 1 : 0,
				files_external: config.value.files_external,
				files_group_folders: config.value.files_group_folders ? 1 : 0,
				files_size: config.value.files_size,
				files_office: config.value.files_office ? 1 : 0,
				files_pdf: config.value.files_pdf ? 1 : 0,
				files_open_result_directly: config.value.files_open_result_directly ? 1 : 0,
			},
		})
		config.value = data
	} catch (error) {
		logger.error('Failed to save Files FullTextSearch settings', { error })
	}
}
</script>
