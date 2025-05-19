import { Component } from '@angular/core';
import { ApiMembersGet200ResponseInner } from '../generated/api/model/apiMembersGet200ResponseInner';
import { DataProviderService } from '../data-provider.service';
import { NgIf } from '@angular/common';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner'
import {MembersListComponent} from "../members-list/members-list.component";

@Component({
  selector: 'app-members-list-page',
  imports: [NgIf, MatProgressSpinnerModule, MembersListComponent],
  templateUrl: './members-list-page.component.html',
  standalone: true,
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
