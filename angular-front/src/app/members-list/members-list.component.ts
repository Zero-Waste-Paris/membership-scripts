import { Component, Input, ViewChild, AfterViewInit } from '@angular/core';

import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { ApiMembersGet200ResponseInner } from '../generated/api/model/apiMembersGet200ResponseInner';

@Component({
  selector: 'app-members-list',
  imports: [MatTableModule, MatSortModule],
  templateUrl: './members-list.component.html',
  standalone: true,
  styleUrl: './members-list.component.css'
})
export class MembersListComponent implements AfterViewInit {
	@Input() members: Array<ApiMembersGet200ResponseInner> = [];
	membersDataSource: MatTableDataSource<ApiMembersGet200ResponseInner> = new MatTableDataSource(this.members);

	displayedColumns: string[] = ['lastRegistrationDate', 'firstName', 'email', 'postalCode', 'wantToDo', 'howDidYouKnowZwp', 'firstRegistrationDate', 'isZWProfessional', 'phone', 'additionalEmails'];

	@ViewChild(MatSort) sort!: MatSort;

	ngAfterViewInit() {
		this.members.sort((m1, m2) => {return new Date(m2.lastRegistrationDate).getTime() - new Date(m1.lastRegistrationDate).getTime();});
		this.membersDataSource = new MatTableDataSource(this.members);
		this.membersDataSource.sort = this.sort;
	}

}
