import { Component } from '@angular/core';
import { ApiMembersGet200ResponseInner } from '../generated/api/model/apiMembersGet200ResponseInner';
import { DataProviderService } from '../data-provider.service';
import { MembersListComponent } from '../members-list/members-list.component';
import { NgIf } from '@angular/common';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner'

@Component({
  selector: 'app-members-list-page',
  standalone: true,
  imports: [NgIf, MembersListComponent, MatProgressSpinnerModule],
  templateUrl: './members-list-page.component.html',
  styleUrl: './members-list-page.component.css'
})
export class MembersListPageComponent {
	membersLoaded: boolean = false;
	members: Array<ApiMembersGet200ResponseInner> = [];

	constructor(
		private dataProvider: DataProviderService,
	) {
		this.fetchMembers();
	}

	async fetchMembers() {
		this.members = (await this.dataProvider.getApiMembers()).reverse();
		this.membersLoaded = true;
		console.log("got " + this.members.length + " members");
	}
}
