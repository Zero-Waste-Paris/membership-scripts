import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SlackOutdatedComponent } from './slack-outdated.component';

describe('SlackOutdatedComponent', () => {
  let component: SlackOutdatedComponent;
  let fixture: ComponentFixture<SlackOutdatedComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SlackOutdatedComponent]
    })
    .compileComponents();
    
    fixture = TestBed.createComponent(SlackOutdatedComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
