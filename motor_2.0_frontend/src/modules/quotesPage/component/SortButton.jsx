import React from "react";
import { Col, Dropdown } from "react-bootstrap";
import { Controller } from "react-hook-form";
import Styled from "../quotesStyle";

const SortButton = ({
  quotesLoaded,
  control,
  sortOptions,
  sortBy,
  setSortBy,
  extPath,
}) => {
  return (
    <Col xl={2} lg={2} md={6} sm={6} xs={6}>
      {!quotesLoaded && (
        <Styled.SortContainer>
          <Controller
            control={control}
            name="sort-by"
            defaultValue={sortOptions[0]}
            render={({ onChange, onBlur, value, name }) => (
              <>
                <Dropdown style={{}}>
                  <Dropdown.Toggle
                   id={"sort_option"}
                    style={{
                      border: "none",
                      boxShadow: "none",
                      background: "#fff",
                      color:
                        import.meta.env.VITE_BROKER === "RB"
                          ? "#1966FF"
                          : "#000",
                      fontSize: "14px",
                    }}
                  >
                    Sort
                  </Dropdown.Toggle>
                  <Dropdown.Menu style={{ textAlign: "left" }}>
                    {sortOptions.map((x) => (
                      <div style={{ display: "flex" }} key={x?.id}>
                        <img
                          className="ml-2 mr-1"
                          src={
                            x.id === "2" || x.id === "4"
                              ? `${extPath}/assets/images/sort-up.svg`
                              : `${extPath}/assets/images/sort-down.svg`
                          }
                          alt="arrow"
                        />
                        <Dropdown.Item
                          name={name}
                          value={value}
                          onClick={() => {
                            setSortBy(x.value);
                          }}
                          style={{
                            fontSize: "11px",
                            lineHeight: "20px",
                            padding: "0.25rempx !important",
                            width: "auto",
                          }}
                          className={x?.value === sortBy ? "isActive" : ""}
                        >
                          {" "}
                          {x.label}{" "}
                        </Dropdown.Item>
                      </div>
                    ))}
                  </Dropdown.Menu>
                </Dropdown>
              </>
            )}
          />
        </Styled.SortContainer>
      )}
    </Col>
  );
};

export default SortButton;
