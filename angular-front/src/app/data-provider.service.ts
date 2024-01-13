import { Injectable } from '@angular/core';
import { DefaultService } from './generated/api/api/default.service';
import { Observable, lastValueFrom } from 'rxjs';
import { TimestampedSlackUserList } from './generated/api/model/timestampedSlackUserList';
import { ApiMembersGet200ResponseInner } from './generated/api/model/apiMembersGet200ResponseInner';

@Injectable({
	providedIn: 'root'
})
export class DataProviderService {
	slackAccountsToDeactivate: Promise<TimestampedSlackUserList>|null = null;
	members: Promise<Array<ApiMembersGet200ResponseInner>>|null = null;

	constructor(
		private apiClient: DefaultService,
	) {}

	async getSlackAccountToDeactivateData(): Promise<TimestampedSlackUserList> {
		if (!this.slackAccountsToDeactivate) {
			this.slackAccountsToDeactivate = lastValueFrom(this.apiClient.apiSlackAccountsToDeactivateGet());
		}

		try {
			return await this.slackAccountsToDeactivate;
		} catch(err) {
			console.log("failed to load slack accounts to deactivate: " + JSON.stringify(err));
			throw err;
		}
	}

	async getApiMembers(): Promise<Array<ApiMembersGet200ResponseInner>> {
		if (!this.members) {
			this.members = lastValueFrom(this.apiClient.apiMembersGet());
		}

		try {
			return await this.members;
		} catch(err) {
			console.log("failed to load members: " + JSON.stringify(err));
			throw err;
		}

	}

}
