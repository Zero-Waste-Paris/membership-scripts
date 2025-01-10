import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MySwaggerUiComponent } from './my-swagger-ui.component';

describe('MySwaggerUiComponent', () => {
  let component: MySwaggerUiComponent;
  let fixture: ComponentFixture<MySwaggerUiComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MySwaggerUiComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MySwaggerUiComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
